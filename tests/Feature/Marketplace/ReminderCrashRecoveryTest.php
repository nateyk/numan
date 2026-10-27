<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MarketplacePlanReminder;
use App\Services\ReminderDispatcher;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * A reminder whose claim was written but never completed.
 *
 * The claim is written before the send, precisely so two runs cannot both send. The
 * cost of that ordering is the window this file covers: the process dies, is killed,
 * or the mailer throws between the two, and the row is left with a null sent_at. The
 * claim then blocks every future run from sending that reminder, because a row
 * already exists. Without the reclaim the customer never hears about a plan that is
 * about to lapse — which is worse than double-sending, because nobody notices a
 * message they did not get.
 *
 * What this does NOT model is a queue outage, because there is no queue:
 * MarketplacePlanReminder does not implement ShouldQueue, so Notification::send()
 * delivers both channels inline and the window is milliseconds wide. An earlier
 * draft of this file asserted against a "worker down for three hours" scenario and
 * failed, because the send completes before the assertion runs. That failure is the
 * useful part: it is what corrected the runbook's claim that a queue worker is a
 * hard gate for reminders.
 *
 * Three hours, not the configured sixty minutes, because the hourly schedule runs
 * twice in that time and both runs must decline to re-dispatch until the timeout has
 * passed. A reclaim that fired on the first run after a crash would re-send
 * reminders the previous process had already delivered.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Notification::fake();
});

/**
 * A workspace with an owner and a subscription due a renewal reminder.
 *
 * @return array{0: MarketplaceSubscription, 1: Workspace}
 */
function claimedFixture(float $days = 20 / 24, string $interval = 'yearly'): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $owner = $workspace->owner;

    $endsAt = now()->addDays($days);

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $owner?->id,
        'status' => 'active',
        'billing_interval' => $interval,
        'current_period_start' => $endsAt->copy()->subDays(30),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
        'chapa_tx_ref' => 'NF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
    ]);

    return [$subscription->fresh(['plan', 'workspace.owner']), $workspace];
}

/**
 * Simulate the process dying between the claim and the send completing.
 *
 * A claim with a null sent_at older than the timeout is the only evidence of that
 * crash, which is why sent_at is nullable and why the reclaim looks for exactly this
 * shape.
 */
function abandonTheSend(MarketplaceReminderLog $log, string $when, ?int $attempts = null): MarketplaceReminderLog
{
    MarketplaceReminderLog::where('id', $log->id)->update([
        'created_at' => $when,
        'updated_at' => $when,
        'sent_at' => null,
        // Back to claimed, because that is the state a crashed process leaves: the
        // row was written and never marked delivered. Nulling sent_at alone is not
        // enough -- a row already marked sent is not reclaimed, so a test that only
        // clears sent_at silently stops exercising the reclaim on the second run.
        'status' => MarketplaceReminderLog::STATUS_CLAIMED,
    ] + ($attempts === null ? [] : ['attempts' => $attempts]));

    return $log->fresh();
}

function run(): void
{
    test()->artisan('marketplace:send-reminders')->assertSuccessful();
}

// ── the window between claim and send ──────────────────────────────────────

it('leaves a claim unsent when the process dies before the send', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    // The claim exists, which is what stops the next run sending it again — and
    // nobody has been told anything.
    expect($log)->not->toBeNull()
        ->and(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_CLAIMED)->count())->toBe(1);

    Notification::assertNothingSent();
});

it('does not re-send on either hourly run inside the timeout', function () {
    config(['marketplace.reminder_claim_timeout_minutes' => 60]);

    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    abandonTheSend($log, now()->subMinutes(30)->toDateTimeString());

    // Twice inside the 60-minute timeout. Both must decline: the previous process may
    // still have been mid-send when it died, and re-dispatching now risks a second
    // copy for a message that already arrived.
    run();
    run();

    Notification::assertNothingSent();
    expect(MarketplaceReminderLog::whereNull('sent_at')->count())->toBe(1);
});

it('re-sends exactly once when the claim is past the timeout', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    abandonTheSend($log, now()->subHours(3)->toDateTimeString());

    run();

    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);

    expect(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(1)
        ->and(MarketplaceReminderLog::whereNull('sent_at')->count())->toBe(0);
});

it('sends nothing further on every run after recovery', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    abandonTheSend($log, now()->subHours(3)->toDateTimeString());

    // The recovery run, then four more.
    for ($i = 0; $i < 5; $i++) {
        run();
    }

    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);

    // Three rows, not one: a yearly subscription twenty hours out is inside the
    // 1d, 3d and 7d windows at once, so the run sent one and recorded the other two
    // as skipped. Only one row was ever eligible to send, and it sent once.
    expect(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(1)
        ->and(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SKIPPED)->count())->toBe(2)
        ->and(MarketplaceReminderLog::whereNull('sent_at')->count())->toBe(0);
});

it('does not double-send when two runs race after the timeout', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    abandonTheSend($log, now()->subHours(3)->toDateTimeString());

    run();
    run();

    // The unique key settles it, not a lock.
    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);
});

it('counts the reclaim as an attempt', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    abandonTheSend($log, now()->subHours(3)->toDateTimeString());

    run();

    // 0 -> 1 across the failed attempt, which is how a third failure is eventually
    // recognised and given up on rather than retried forever.
    expect($log->fresh()->attempts)->toBe(1);
});

// ── repeated failures ─────────────────────────────────────────────────────

it('gives up after three attempts and warns with ids only', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    // Three runs, an hour apart, each reclaiming a claim that never delivered. The
    // attempts count is carried across explicitly, because in production the claim
    // is written once and re-dispatched in place -- there is no fresh claim per run.
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        abandonTheSend($log, now()->subHours($attempt)->toDateTimeString(), attempts: $attempt - 1);

        run();
    }

    // Past reminder_max_attempts.
    abandonTheSend($log, now()->subHours(4)->toDateTimeString(), attempts: 3);

    $logged = [];
    Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    run();

    $warning = implode("\n", $logged);
    $owner = $subscription->workspace->owner;

    expect($warning)->toContain('Marketplace reminder abandoned')
        ->and($warning)->toContain((string) $subscription->id)
        // Ids only. A customer's name in a log warning that gets shipped to an error
        // tracker is the exact leak this workstream exists to remove.
        ->and($warning)->not->toContain($owner->name ?? 'IMPOSSIBLE')
        ->and($warning)->not->toContain($owner->email ?? 'IMPOSSIBLE');
});

it('stops retrying a row it has given up on', function () {
    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    MarketplaceReminderLog::where('id', $log->id)->update([
        'created_at' => now()->subHours(5)->toDateTimeString(),
        'sent_at' => null,
        'attempts' => 3,
    ]);

    run();
    run();
    run();

    // Abandoned, so it is no longer pending and not retried on every future run for
    // the life of the table.
    Notification::assertNothingSent();
    expect(MarketplaceReminderLog::whereNull('sent_at')->count())->toBe(0);
});

// ── several workspaces at once ─────────────────────────────────────────────

it('recovers every workspace at once, one send each', function () {
    $subscriptions = [];

    for ($i = 0; $i < 3; $i++) {
        [$subscription] = claimedFixture();
        abandonTheSend(
            app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d'),
            now()->subHours(3)->toDateTimeString(),
        );
        $subscriptions[] = $subscription;
    }

    run();

    foreach ($subscriptions as $subscription) {
        Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);
    }

    // Three claims, three sends. Not nine: the reclaim re-dispatches each row once
    // and marks it sent in the same pass.
    expect(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(3)
        ->and(MarketplaceReminderLog::whereNull('sent_at')->count())->toBe(0);
});

it('notifies every billing manager once each, not once per recipient per run', function () {
    [$subscription] = claimedFixture();
    $workspace = $subscription->workspace;

    // Granted billing explicitly, so one claim has two recipients.
    $treasurer = User::factory()->create();
    Profile::factory()->create([
        'user_id' => $treasurer->id,
        'account_type' => 'business',
        'display_name' => $treasurer->name,
        'phone' => '+251900000000',
    ]);
    $workspace->users()->attach($treasurer->id, [
        'role' => 'member',
        'permissions' => json_encode(['manage_billing']),
    ]);

    abandonTheSend(
        app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d'),
        now()->subHours(3)->toDateTimeString(),
    );

    for ($i = 0; $i < 3; $i++) {
        run();
    }

    // Three runs, two recipients, one send each.
    Notification::assertSentToTimes($workspace->owner, MarketplacePlanReminder::class, 1);
    Notification::assertSentToTimes($treasurer->fresh(), MarketplacePlanReminder::class, 1);
});

// ── the mail channel is not load-bearing ───────────────────────────────────

it('still records the reminder in-app when mail is not delivered', function () {
    // Because the send is inline rather than queued, the database channel is written
    // by the same call that would send the mail. A mailer configured to `log` — or an
    // SMTP outage — therefore still leaves the recipient a notification in the app.
    // This is the reason there are two channels, and it is worth a test because it is
    // the property that would be lost if somebody queued the notification.
    Notification::fake();

    [$subscription] = claimedFixture();
    $log = app(ReminderDispatcher::class)->claim($subscription, 'renewal_1d');

    app(ReminderDispatcher::class)->dispatch($log->fresh());

    Notification::assertSentTo(
        $subscription->workspace->owner,
        MarketplacePlanReminder::class,
        fn ($notification, array $channels): bool => $channels === ['database', 'mail'],
    );
});

it('uses both channels, so neither is a single point of failure', function () {
    $notification = new MarketplacePlanReminder(
        (new MarketplaceSubscription)->setRelation('plan', MarketplacePlan::where('slug', 'standard')->first()),
        'renewal_1d',
    );

    expect($notification->via(new User))->toBe(['database', 'mail']);
});
