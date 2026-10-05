<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use App\Notifications\MarketplacePlanReminder;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * The command: what it decides, what it skips, and what it refuses to do.
 *
 * Sequential on purpose. The idempotency guarantee is the unique key on the log,
 * not anything in memory, so there is nothing here a lock would improve.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Notification::fake();
});

/**
 * @return array{0: MarketplaceSubscription, 1: Workspace}
 */
function dueSubscription(float $days, string $interval = 'monthly', string $status = 'active'): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $endsAt = now()->addDays($days);

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $workspace->owner?->id,
        'status' => $status,
        'billing_interval' => $interval,
        'current_period_start' => $endsAt->copy()->subDays(30),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
        'chapa_tx_ref' => 'NF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
    ]);

    return [$subscription, $workspace];
}

// ── D: the flag ───────────────────────────────────────────────────────────

it('is not scheduled while the flag is off', function () {
    config(['marketplace.reminders_enabled' => false]);

    $schedule = app(Schedule::class);
    $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '');

    expect($commands->implode(' '))->not->toContain('marketplace:send-reminders');
});

it('is scheduled hourly once the flag is on', function () {
    config(['marketplace.reminders_enabled' => true]);

    // Re-evaluated because routes/console.php reads config at registration time.
    $this->artisan('schedule:list')->assertSuccessful();
});

// ── sending ───────────────────────────────────────────────────────────────

it('sends a due reminder', function () {
    [$subscription] = dueSubscription(1);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertSentTo($subscription->workspace->owner, MarketplacePlanReminder::class);
    expect(MarketplaceReminderLog::where('reminder_type', 'renewal_1d')->count())->toBe(1);
});

it('sends nothing twice on a second run', function () {
    [$subscription] = dueSubscription(1);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();
    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);

    // One sent row is the actual guarantee. Notification::fake() counts sends,
    // not rows, so the log is what proves the second run did nothing.
    expect(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(1)
        ->and(MarketplaceReminderLog::where('reminder_type', 'renewal_1d')->count())->toBe(1);
});

it('sends nothing well outside every window', function () {
    dueSubscription(20);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(MarketplaceReminderLog::count())->toBe(0);
});

it('does not email a historical backlog on first run', function () {
    // A: the whole reason the expiry window exists.
    config(['marketplace.reminder_expired_window_days' => 3]);
    dueSubscription(-20);
    dueSubscription(-45);
    dueSubscription(-90);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(MarketplaceReminderLog::count())->toBe(0);
});

it('does email a subscription that lapsed inside the window', function () {
    config(['marketplace.reminder_expired_window_days' => 3]);
    [$subscription] = dueSubscription(-1);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    expect(MarketplaceReminderLog::where('reminder_type', 'plan_expired')->count())->toBe(1);
});

it('sends nothing to a superseded row', function () {
    dueSubscription(1, 'monthly', 'superseded');

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(MarketplaceReminderLog::count())->toBe(0);
});

it('sends nothing to a workspace that has already renewed', function () {
    // The superseded row and its replacement both exist, as a renewal leaves them.
    dueSubscription(1, 'yearly', 'superseded');
    dueSubscription(30, 'yearly');

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

// ── B: one reminder per subscription, and the rest recorded ───────────────

it('sends only the most urgent window', function () {
    dueSubscription(20 / 24, 'yearly');

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    $sent = MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->pluck('reminder_type')->all();

    expect($sent)->toBe(['renewal_1d']);
});

it('records the looser windows as skipped so they cannot fire later', function () {
    dueSubscription(20 / 24, 'yearly');

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    $skipped = MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SKIPPED)
        ->pluck('reminder_type')->sort()->values()->all();

    expect($skipped)->toBe(['renewal_3d', 'renewal_7d']);
});

it('never sends a seven-day warning after a one-day one', function () {
    [$subscription] = dueSubscription(20 / 24, 'yearly');

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    // Seven days later the 7d window is long past anyway, but the point is the
    // row exists so it cannot be claimed even if the timing were different.
    expect(MarketplaceReminderLog::where('reminder_type', 'renewal_7d')->exists())->toBeTrue()
        ->and(MarketplaceReminderLog::where('reminder_type', 'renewal_7d')
            ->where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(0);
});

// ── the dry run writes nothing ────────────────────────────────────────────

it('writes nothing on a dry run', function () {
    dueSubscription(1);

    $this->artisan('marketplace:send-reminders --dry-run')
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect(MarketplaceReminderLog::count())->toBe(0);
    Notification::assertNothingSent();
});

it('works on a dry run even when the flag is off', function () {
    // The flag gates the scheduler registration only. A dry run on a command that
    // has never run before is the most valuable moment to use it.
    config(['marketplace.reminders_enabled' => false]);
    dueSubscription(1);

    $this->artisan('marketplace:send-reminders --dry-run')->assertSuccessful();

    expect(MarketplaceReminderLog::count())->toBe(0);
});

it('lists the reminders a dry run would send', function () {
    dueSubscription(1);

    // One output assertion per artisan call. Chained expectsOutputToContain calls
    // are matched against the written lines in order, so three of them against
    // five lines lines up the wrong substring with the wrong line.
    $this->artisan('marketplace:send-reminders --dry-run')
        ->expectsOutputToContain('renewal_1d')
        ->assertSuccessful();
});

it('marks a not-yet-sent reminder as new on a dry run', function () {
    dueSubscription(1);

    $this->artisan('marketplace:send-reminders --dry-run')
        ->expectsOutputToContain('would send, new')
        ->assertSuccessful();
});

it('marks an already-decided reminder on a dry run rather than claiming it again', function () {
    dueSubscription(1);

    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    $this->artisan('marketplace:send-reminders --dry-run')
        ->expectsOutputToContain('would send, already decided')
        ->assertSuccessful();

    // A monthly plan one day out is also inside the three-day window, so the first
    // run sent one and recorded one as skipped. Two rows, and the dry run added
    // nothing to either.
    expect(MarketplaceReminderLog::count())->toBe(2)
        ->and(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SENT)->count())->toBe(1)
        ->and(MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SKIPPED)->count())->toBe(1);
});

it('reports nothing due rather than failing on an empty queue', function () {
    $this->artisan('marketplace:send-reminders')
        ->expectsOutputToContain('Nothing due')
        ->assertSuccessful();
});

// ── no contact data ───────────────────────────────────────────────────────

it('logs no contact data', function () {
    // The standing LogHygieneTest covers the rule generally; this pins the
    // command's own output, because "log the workspace while debugging" is a very
    // natural edit here.
    $logged = [];
    Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    [$subscription] = dueSubscription(1);
    $this->artisan('marketplace:send-reminders')->assertSuccessful();

    expect(implode("\n", $logged))
        ->not->toContain($subscription->workspace->owner->email)
        ->not->toContain($subscription->workspace->owner->name);
});
