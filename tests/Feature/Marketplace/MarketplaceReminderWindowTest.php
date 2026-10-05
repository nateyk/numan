<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use App\Services\MarketplaceReminderWindow;
use Carbon\CarbonInterface;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Which reminders a subscription is due.
 *
 * Sequential on purpose: these assert against an injected "now", so they do not
 * depend on wall-clock time beyond the seed data. Nothing here touches the wall
 * clock inside a test body.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/**
 * A subscription ending at an exact offset from now.
 *
 * @return array{0: MarketplaceSubscription, 1: CarbonInterface}
 */
function subEndingIn(float $days, string $interval = 'monthly', string $status = 'active'): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $now = now();

    $endsAt = $now->copy()->addDays($days);

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => null,
        'status' => $status,
        'billing_interval' => $interval,
        'current_period_start' => $endsAt->copy()->subDays(30),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
        'chapa_tx_ref' => 'NF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
    ]);

    return [$subscription, $now];
}

/**
 * @return array{types: list<string>, skipped: list<string>}
 */
function dueFor(MarketplaceSubscription $subscription, $now): array
{
    return MarketplaceReminderWindow::due($subscription, $now);
}

it('sends only the most urgent window reached', function () {
    // A yearly plan with twenty hours left, which is inside renewal_1d and also
    // inside the 3-day and 7-day windows. Only the most urgent goes out; the
    // naive version sends three emails about one subscription.
    [$subscription, $now] = subEndingIn(20 / 24, 'yearly');

    $due = dueFor($subscription, $now);

    expect($due['types'])->toBe(['renewal_1d']);
});

it('records the looser windows it passed over as skipped', function () {
    [$subscription, $now] = subEndingIn(20 / 24, 'yearly');

    $due = dueFor($subscription, $now);

    // Marked skipped so they cannot fire later, which would put a "7 days left"
    // email after a "1 day left" one.
    expect($due['skipped'])->toBe(['renewal_3d', 'renewal_7d']);
});

it('sends the seven-day warning when nothing more urgent is open', function () {
    [$subscription, $now] = subEndingIn(5, 'yearly');

    $due = dueFor($subscription, $now);

    expect($due['types'])->toBe(['renewal_7d'])
        ->and($due['skipped'])->toBe([]);
});

it('moves to the three-day warning inside three days', function () {
    [$subscription, $now] = subEndingIn(2.9, 'yearly');

    $due = dueFor($subscription, $now);

    expect($due['types'])->toBe(['renewal_3d'])
        ->and($due['skipped'])->toBe(['renewal_7d']);
});

it('sends nothing well outside every window', function () {
    [$subscription, $now] = subEndingIn(20, 'yearly');

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('gives a weekly plan the one-day warning and nothing looser', function () {
    // A seven-day warning on a seven-day plan reads as broken, so weekly is not
    // in the 7-day or 3-day windows at all.
    [$subscription, $now] = subEndingIn(5, 'weekly');

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('gives a weekly plan the one-day warning inside a day', function () {
    [$subscription, $now] = subEndingIn(0.8, 'weekly');

    expect(dueFor($subscription, $now)['types'])->toBe(['renewal_1d']);
});

it('gives a monthly plan three-day and one-day warnings but not seven', function () {
    [$three, $now] = subEndingIn(2.5, 'monthly');
    [$seven, $now2] = subEndingIn(5, 'monthly');

    expect(dueFor($three, $now)['types'])->toBe(['renewal_3d'])
        ->and(dueFor($seven, $now2)['types'])->toBe([]);
});

// ── A: the expiry reminder has a window ───────────────────────────────────

it('sends the expiry reminder just after ends_at', function () {
    [$subscription, $now] = subEndingIn(-0.5);

    expect(dueFor($subscription, $now)['types'])->toBe(['plan_expired']);
});

it('sends the expiry reminder on the ends_at boundary', function () {
    [$subscription, $now] = subEndingIn(0);

    // Inclusive, so an hourly run cannot fall between ticks and miss it.
    expect(dueFor($subscription, $now)['types'])->toBe(['plan_expired']);
});

it('still sends the expiry reminder just inside three days', function () {
    config(['marketplace.reminder_expired_window_days' => 3]);
    [$subscription, $now] = subEndingIn(-2.9);

    expect(dueFor($subscription, $now)['types'])->toBe(['plan_expired']);
});

it('sends the expiry reminder exactly on the window boundary', function () {
    config(['marketplace.reminder_expired_window_days' => 3]);

    // Pinned rather than relative. ends_at is stored to the second while now()
    // carries microseconds, so a value built as now()->subDays(3) lands a
    // fraction early and fails a strict comparison. Both sides are truncated here
    // so the boundary really is the boundary.
    $now = now()->startOfSecond();
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => $now->copy()->subDays(30),
        'current_period_ends_at' => $now->copy()->subDays(3),
        'ends_at' => $now->copy()->subDays(3),
        'chapa_tx_ref' => 'NF-BOUNDARY',
    ]);

    expect(dueFor($subscription->fresh(), $now)['types'])->toBe(['plan_expired']);
});

it('sends nothing to a subscription that expired long ago', function () {
    // The whole point of the window. The first run after enabling must not email
    // every lapsed row in the database.
    config(['marketplace.reminder_expired_window_days' => 3]);
    [$subscription, $now] = subEndingIn(-20);

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('sends nothing to a subscription that expired four days ago', function () {
    config(['marketplace.reminder_expired_window_days' => 3]);
    [$subscription, $now] = subEndingIn(-4);

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('never sends both an expiry and a renewal reminder at once', function () {
    [$subscription, $now] = subEndingIn(-1, 'yearly');

    $types = dueFor($subscription, $now)['types'];

    expect($types)->toBe(['plan_expired'])
        ->and($types)->not->toContain('renewal_1d');
});

// ── superseded and renewed rows ────────────────────────────────────────────

it('sends nothing to a superseded row', function () {
    // A renewal supersedes the prior row and creates a new one. This is what stops
    // a workspace being reminded about a period it has already renewed, with no
    // date arithmetic needed.
    [$subscription, $now] = subEndingIn(1.2, 'yearly', 'superseded');

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('sends nothing to a lapsed or cancelled row', function (string $status) {
    [$subscription, $now] = subEndingIn(1, 'monthly', $status);

    expect(dueFor($subscription, $now)['types'])->toBe([]);
})->with(['expired', 'cancelled', 'rejected']);

it('sends nothing to a row with no end date', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
    ]);

    expect($subscription->ends_at)->toBeNull()
        ->and(dueFor($subscription, now())['types'])->toBe([]);
});

it('sends nothing for an unrecognised interval rather than guessing', function () {
    [$subscription, $now] = subEndingIn(0.5, 'fortnightly');

    expect(dueFor($subscription, $now)['types'])->toBe([]);
});

it('orders the reminder types most urgent first', function () {
    expect(MarketplaceReminderWindow::types())
        ->toBe(['plan_expired', 'renewal_1d', 'renewal_3d', 'renewal_7d']);
});
