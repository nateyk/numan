<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Services\MarketplaceFulfillmentService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->fulfillment = app(MarketplaceFulfillmentService::class);
});

function prepTransaction(int $workspaceId, int $userId, int $planId, string $interval, float $amount, string $txRef): MarketplacePaymentTransaction
{
    return MarketplacePaymentTransaction::create([
        'workspace_id' => $workspaceId,
        'plan_id' => $planId,
        'user_id' => $userId,
        'tx_ref' => $txRef,
        'amount_etb' => $amount,
        'currency' => 'ETB',
        'billing_interval' => $interval,
        'status' => 'initialized',
        'mode' => 'test',
    ]);
}

function prepVerifiedResponse(float $amount): array
{
    return [
        'status' => 'success',
        'data' => [
            'status' => 'success',
            'currency' => 'ETB',
            'amount' => $amount,
            'reference' => 'chapa_ref_'.uniqid(),
        ],
    ];
}

it('rejects a second subscription for the same chapa_tx_ref', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_unique_test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $insertAgain = fn () => DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_unique_test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $insertAgain())->toThrow(QueryException::class);

    expect(MarketplaceSubscription::where('chapa_tx_ref', 'NF-pro_unique_test')->count())->toBe(1);
});

it('rejects a second receipt for the same transaction', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = prepTransaction($workspace->id, $user->id, $plan->id, 'monthly', 999, 'NF-pro_receipt_uniq');

    $receipt = fn () => MarketplacePaymentReceipt::create([
        'transaction_id' => $tx->id,
        'subscription_id' => null,
        'workspace_id' => $workspace->id,
        'amount_etb' => 999,
        'currency' => 'ETB',
        'invoice_number' => 'NF-'.now()->format('Ymd').'-'.uniqid(),
        'issued_at' => now(),
    ]);

    $receipt();

    expect(fn () => $receipt())->toThrow(QueryException::class);

    expect(MarketplacePaymentReceipt::where('transaction_id', $tx->id)->count())->toBe(1);
});

it('allows multiple subscriptions with a null chapa_tx_ref', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $row = fn () => DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'canceled',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now(),
        'ends_at' => now(),
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'manual',
        'chapa_tx_ref' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row();
    $row();
    $row();

    expect(MarketplaceSubscription::whereNull('chapa_tx_ref')->count())->toBe(3);
});

it('renewal extends the current period instead of overwriting it', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    // Existing monthly subscription that ends in 25 days.
    $existingEnd = now()->addDays(25);
    DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(5),
        'current_period_ends_at' => $existingEnd,
        'ends_at' => $existingEnd,
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_monthly_original',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Buy a weekly pass now. It must extend, not restart.
    $tx = prepTransaction($workspace->id, $user->id, $plan->id, 'weekly', 349, 'NF-pro_weekly_renewal');
    $this->fulfillment->fulfill($tx, prepVerifiedResponse(349));

    $new = MarketplaceSubscription::where('chapa_tx_ref', 'NF-pro_weekly_renewal')->first();

    expect($new)->not->toBeNull();
    // New period starts at the old end date and runs 7 days from there.
    expect((int) $new->current_period_start->diffInSeconds($existingEnd, false))->toBe(0);
    expect((int) $new->current_period_start->diffInDays($new->current_period_ends_at))->toBe(7);
    expect(abs((int) $new->current_period_ends_at->diffInSeconds($existingEnd, false)))
        ->toBeBetween(7 * 86400 - 5, 7 * 86400 + 5);
});

it('keeps the superseded subscription end date so no days are lost', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $existingEnd = now()->addDays(25);
    DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(5),
        'current_period_ends_at' => $existingEnd,
        'ends_at' => $existingEnd,
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_monthly_original_2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tx = prepTransaction($workspace->id, $user->id, $plan->id, 'weekly', 349, 'NF-pro_weekly_renewal_2');
    $this->fulfillment->fulfill($tx, prepVerifiedResponse(349));

    $old = MarketplaceSubscription::where('chapa_tx_ref', 'NF-pro_monthly_original_2')->first();

    expect($old->status)->toBe('superseded');
    // End date untouched — the days already paid for are still there.
    expect((int) $old->ends_at->diffInSeconds($existingEnd, false))->toBe(0);
});

it('access remains continuous after an early renewal', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $existingEnd = now()->addDays(25);
    DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(5),
        'current_period_ends_at' => $existingEnd,
        'ends_at' => $existingEnd,
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_monthly_original_3',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tx = prepTransaction($workspace->id, $user->id, $plan->id, 'weekly', 349, 'NF-pro_weekly_renewal_3');
    $this->fulfillment->fulfill($tx, prepVerifiedResponse(349));

    // Still has an active subscription immediately after the renewal.
    expect($workspace->activeMarketplaceSubscription())->not->toBeNull();

    // And still has one after the old end date passes but before the new one.
    $this->travelTo(now()->addDays(26)->startOfDay());

    expect($workspace->fresh()->activeMarketplaceSubscription())->not->toBeNull();
    expect($workspace->fresh()->activeMarketplaceSubscription()->billing_interval)->toBe('weekly');
});

it('renews an expired subscription from now', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    // Expired 10 days ago.
    DB::table('marketplace_subscriptions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(40),
        'current_period_ends_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10),
        'trial_ends_at' => null,
        'quantity' => 1,
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-pro_expired_original',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($workspace->activeMarketplaceSubscription())->toBeNull();

    $tx = prepTransaction($workspace->id, $user->id, $plan->id, 'weekly', 349, 'NF-pro_weekly_after_expiry');
    $this->fulfillment->fulfill($tx, prepVerifiedResponse(349));

    $new = MarketplaceSubscription::where('chapa_tx_ref', 'NF-pro_weekly_after_expiry')->first();

    // Starts now, not from a date in the past.
    expect((int) abs($new->current_period_start->diffInSeconds(now())))->toBeLessThanOrEqual(2);
    expect((int) $new->current_period_start->diffInDays($new->current_period_ends_at))->toBe(7);
});

it('no longer writes an idempotency_key column', function () {
    expect(Schema::hasColumn('marketplace_payment_transactions', 'idempotency_key'))->toBeFalse();
});
