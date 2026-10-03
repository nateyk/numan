<?php

use App\Models\MarketplacePlan;
use App\Services\MarketplaceBackfillService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->backfill = app(MarketplaceBackfillService::class);
});

/**
 * Insert a legacy subscription that predates the ends_at fix.
 */
function insertLegacySubscription(int $workspaceId, int $userId, int $planId, string $interval, $periodEnd): int
{
    return DB::table('marketplace_subscriptions')->insertGetId([
        'workspace_id' => $workspaceId,
        'plan_id' => $planId,
        'user_id' => $userId,
        'status' => 'active',
        'billing_interval' => $interval,
        'current_period_start' => now()->subDays(8),
        'current_period_ends_at' => $periodEnd,
        'ends_at' => null,
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('sets ends_at on active subscriptions where it was null', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $subId = insertLegacySubscription($workspace->id, $user->id, $plan->id, 'monthly', now()->addDays(22));

    expect(DB::table('marketplace_subscriptions')->where('id', $subId)->value('ends_at'))->toBeNull();

    $this->backfill->backfillSubscriptionEndsAt();

    $endsAt = DB::table('marketplace_subscriptions')->where('id', $subId)->value('ends_at');
    $periodEnd = DB::table('marketplace_subscriptions')->where('id', $subId)->value('current_period_ends_at');

    expect($endsAt)->not->toBeNull();
    expect($endsAt)->toBe($periodEnd);
});

it('leaves already expired plans inactive after backfill', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    // Ends yesterday — currently treated as active because ends_at is null.
    $subId = insertLegacySubscription($workspace->id, $user->id, $plan->id, 'weekly', now()->subDay());

    expect($workspace->activeMarketplaceSubscription())->not->toBeNull();

    $this->backfill->backfillSubscriptionEndsAt();

    expect($workspace->activeMarketplaceSubscription())->toBeNull();
    expect(DB::table('marketplace_subscriptions')->where('id', $subId)->value('ends_at'))->not->toBeNull();
});

it('keeps unexpired plans active after backfill', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $subId = insertLegacySubscription($workspace->id, $user->id, $plan->id, 'weekly', now()->addDays(5));

    $this->backfill->backfillSubscriptionEndsAt();

    expect($workspace->activeMarketplaceSubscription())->not->toBeNull();
    expect(DB::table('marketplace_subscriptions')->where('id', $subId)->value('ends_at'))->not->toBeNull();
});

it('backfills billing_interval by parsing tx_ref', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $txId = DB::table('marketplace_payment_transactions')->insertGetId([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_68f3a1b2c3d4e5',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'status' => 'verified',
        'mode' => 'test',
        'billing_interval' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->backfill->backfillTransactionIntervals();

    expect(DB::table('marketplace_payment_transactions')->where('id', $txId)->value('billing_interval'))
        ->toBe('weekly');
});

it('falls back to matching amount_etb when tx_ref cannot be parsed', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $txId = DB::table('marketplace_payment_transactions')->insertGetId([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'legacy_opaque_reference_123',
        'amount_etb' => 3999, // matches Pro yearly
        'currency' => 'ETB',
        'status' => 'verified',
        'mode' => 'test',
        'billing_interval' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($this->backfill->parseIntervalFromTxRef('legacy_opaque_reference_123'))->toBeNull();

    $this->backfill->backfillTransactionIntervals();

    expect(DB::table('marketplace_payment_transactions')->where('id', $txId)->value('billing_interval'))
        ->toBe('yearly');
});

it('leaves billing_interval null when neither tx_ref nor amount can be resolved', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $txId = DB::table('marketplace_payment_transactions')->insertGetId([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'opaque_ref_xyz',
        'amount_etb' => 777, // matches no plan price
        'currency' => 'ETB',
        'status' => 'verified',
        'mode' => 'test',
        'billing_interval' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->backfill->backfillTransactionIntervals();

    expect(DB::table('marketplace_payment_transactions')->where('id', $txId)->value('billing_interval'))
        ->toBeNull();
});

it('writes a csv report for subscriptions granted the wrong duration without changing them', function () {
    Storage::fake('local');

    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    // A weekly payment that was (incorrectly) granted 30 days by the old code.
    $subId = DB::table('marketplace_subscriptions')->insertGetId([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'canceled',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(30),
        'current_period_ends_at' => now()->subDays(1),
        'ends_at' => now()->subDay(),
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-weekly_wrong_duration_ref',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('marketplace_payment_transactions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_wrong_duration_ref',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'status' => 'verified',
        'mode' => 'test',
        'billing_interval' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->backfill->backfillTransactionIntervals();

    $path = 'reports/wrong-duration-subscriptions-'.now()->format('Ymd').'.csv';
    Storage::disk('local')->assertExists($path);

    $csv = Storage::disk('local')->get($path);
    expect($csv)->toContain('subscription_id');
    expect($csv)->toContain('days_actually_granted');
    expect($csv)->toContain('7'); // expected days for weekly
    expect($csv)->toContain('29'); // ~30 days actually granted

    // The mismatched subscription must be left untouched.
    $row = DB::table('marketplace_subscriptions')->where('id', $subId)->first();
    expect($row->billing_interval)->toBe('monthly');
    expect($row->current_period_ends_at)->not->toBeNull();
});

it('does not overwrite an interval that was already set', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $txId = DB::table('marketplace_payment_transactions')->insertGetId([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_already_set',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'status' => 'verified',
        'mode' => 'test',
        'billing_interval' => 'yearly', // pre-existing value
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->backfill->backfillTransactionIntervals();

    expect(DB::table('marketplace_payment_transactions')->where('id', $txId)->value('billing_interval'))
        ->toBe('yearly');
});
