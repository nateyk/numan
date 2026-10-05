<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MarketplacePlanReminder;
use App\Services\ReminderDispatcher;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * Claiming, dispatching and reclaiming.
 *
 * The ordering is the whole design: the log row is written before the
 * notification is queued, so two concurrent runs cannot both send. That leaves a
 * claim with a null sent_at when the queue is down, and reclaim() is what stops
 * those being swallowed forever.
 *
 * Sequential on purpose -- these reason about a single subscription's log rows and
 * the SQLite suite takes no meaningful lock anyway.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Notification::fake();
});

/**
 * @return array{0: MarketplaceSubscription, 1: ReminderDispatcher}
 */
function dispatcherFixture(float $days = 1, string $interval = 'monthly'): array
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

    return [$subscription->fresh(['plan', 'workspace.owner']), app(ReminderDispatcher::class)];
}

// ── claiming ──────────────────────────────────────────────────────────────

it('claims a reminder once', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    expect($dispatcher->claim($subscription, 'renewal_1d'))->not->toBeNull();
});

it('refuses to claim the same reminder twice', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->claim($subscription, 'renewal_1d');

    // The unique key is the idempotency guarantee, so the second run simply gets
    // nothing to do rather than a second send.
    expect($dispatcher->claim($subscription, 'renewal_1d'))->toBeNull();
    expect(MarketplaceReminderLog::where('reminder_type', 'renewal_1d')->count())->toBe(1);
});

it('allows the same reminder type on a different subscription', function () {
    [$first, $dispatcher] = dispatcherFixture();
    [$second] = dispatcherFixture();

    expect($dispatcher->claim($first, 'renewal_1d'))->not->toBeNull()
        ->and($dispatcher->claim($second, 'renewal_1d'))->not->toBeNull();
});

it('allows different reminder types on one subscription', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->claim($subscription, 'renewal_1d');

    expect($dispatcher->claim($subscription, 'renewal_3d'))->not->toBeNull();
});

it('records a skipped window so it can never fire later', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->claim($subscription, 'renewal_1d');
    $dispatcher->skip($subscription, ['renewal_3d', 'renewal_7d']);

    $skipped = MarketplaceReminderLog::where('status', MarketplaceReminderLog::STATUS_SKIPPED)->get();

    expect($skipped)->toHaveCount(2)
        ->and($skipped->pluck('reminder_type')->sort()->values()->all())
        ->toBe(['renewal_3d', 'renewal_7d']);
});

it('refuses to send a window that was skipped', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->skip($subscription, ['renewal_7d']);

    // The row is what stops a seven-day warning arriving three days after the
    // one-day warning that replaced it.
    expect($dispatcher->claim($subscription, 'renewal_7d'))->toBeNull();
});

it('does not double-count a window that was skipped twice', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    expect($dispatcher->skip($subscription, ['renewal_7d']))->toBe(1)
        ->and($dispatcher->skip($subscription, ['renewal_7d']))->toBe(0);
});

// ── dispatching ───────────────────────────────────────────────────────────

it('marks a claim delivered once it has been handed to the queue', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');
    $dispatcher->dispatch($log);

    $log->refresh();

    expect($log->status)->toBe(MarketplaceReminderLog::STATUS_SENT)
        ->and($log->sent_at)->not->toBeNull();
});

it('notifies everybody who can manage billing', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');
    $dispatcher->dispatch($log);

    Notification::assertSentTo(
        $subscription->workspace->owner,
        MarketplacePlanReminder::class
    );
});

it('records an attempt each time it dispatches', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');
    $dispatcher->dispatch($log);

    expect($log->fresh()->attempts)->toBe(1);
});

it('drops the claim when the subscription goes, rather than retrying forever', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');
    $subscription->delete();

    // The foreign key cascades. A workspace deleted with an open claim cannot
    // leave a row behind for reclaim() to pick up on every future run, which is
    // what would otherwise happen with a nullOnDelete.
    expect(MarketplaceReminderLog::where('id', $log->id)->exists())->toBeFalse()
        ->and($dispatcher->reclaim())->toBe(['reclaimed' => 0, 'abandoned' => 0]);
});

// ── C: reclaiming a claim the queue never delivered ───────────────────────

it('leaves a fresh claim alone', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->claim($subscription, 'renewal_1d');

    expect($dispatcher->reclaim())->toBe(['reclaimed' => 0, 'abandoned' => 0]);
});

it('re-dispatches a claim left undelivered by a queue outage', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');

    // Simulate the queue being down: the claim was written, the send never
    // completed, so sent_at is still null and the row is an hour old.
    DB::table('marketplace_reminder_logs')
        ->where('id', $log->id)
        ->update([
            'created_at' => now()->subMinutes(61),
            'sent_at' => null,
            'status' => MarketplaceReminderLog::STATUS_CLAIMED,
        ]);

    expect($dispatcher->reclaim()['reclaimed'])->toBe(1);

    Notification::assertSentTo($subscription->workspace->owner, MarketplacePlanReminder::class);
});

it('sends exactly once after the worker recovers', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');

    DB::table('marketplace_reminder_logs')
        ->where('id', $log->id)
        ->update(['created_at' => now()->subMinutes(61), 'sent_at' => null]);

    $dispatcher->reclaim();
    $dispatcher->reclaim();
    $dispatcher->reclaim();

    // Worker back up, then three more runs. The reclaim marked it sent, so it is
    // no longer a pending claim and nothing goes out again.
    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);
});

it('abandons a reminder that keeps failing, and warns with ids only', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');

    $logged = [];
    Illuminate\Support\Facades\Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    // Three failures' worth of attempts, an hour old.
    DB::table('marketplace_reminder_logs')->where('id', $log->id)->update([
        'created_at' => now()->subMinutes(61),
        'sent_at' => null,
        'attempts' => 3,
    ]);

    expect($dispatcher->reclaim()['abandoned'])->toBe(1)
        ->and($log->fresh()->status)->toBe(MarketplaceReminderLog::STATUS_SKIPPED);

    $warning = implode("\n", $logged);

    expect($warning)->toContain('Marketplace reminder abandoned')
        ->and($warning)->toContain((string) $subscription->id)
        // An id is enough to chase it. A customer's name in a log warning that
        // gets shipped to an error tracker is not.
        ->and($warning)->not->toContain($subscription->workspace->owner?->name ?? 'IMPOSSIBLE')
        ->and($warning)->not->toContain($subscription->workspace->owner?->email ?? 'IMPOSSIBLE');
});

it('does not reclaim a row that is already delivered', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $log = $dispatcher->claim($subscription, 'renewal_1d');
    $dispatcher->dispatch($log);

    DB::table('marketplace_reminder_logs')->where('id', $log->id)
        ->update(['created_at' => now()->subMinutes(120)]);

    expect($dispatcher->reclaim()['reclaimed'])->toBe(0);
});

it('does not reclaim a skipped row', function () {
    [$subscription, $dispatcher] = dispatcherFixture();

    $dispatcher->skip($subscription, ['renewal_3d']);

    expect($dispatcher->reclaim())->toBe(['reclaimed' => 0, 'abandoned' => 0]);
});