<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

it('fulfills a pending transaction via the reconcile command', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_reconcile_test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'pending',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-pro_reconcile_test' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 449, 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);

    $this->artisan('chapa:reconcile')
        ->expectsOutputToContain('Fulfilled successfully')
        ->assertSuccessful();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeTrue();
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

it('does not grant access for a pending unverified transaction', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_pending_test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'pending',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-pro_pending_test' => Http::response([
            'status' => 'pending',
            'data' => ['status' => 'pending'],
        ], 200),
    ]);

    $this->artisan('chapa:reconcile')
        ->expectsOutputToContain('Not yet verified')
        ->assertSuccessful();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('continues processing other transactions when one fails', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_fail_test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'pending',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_success_test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'pending',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-pro_fail_test' => Http::response([
            'status' => 'failed',
            'data' => ['status' => 'failed'],
        ], 200),
        'api.chapa.co/v1/transaction/verify/NF-pro_success_test' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 449, 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);

    $this->artisan('chapa:reconcile')
        ->expectsOutputToContain('1 fulfilled, 1 failed')
        ->assertSuccessful();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('can reconcile older outstanding payments without silently granting canceled accounts', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $old = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-reconcile-old-safe',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
        'billing_interval' => 'monthly',
    ]);
    DB::table('marketplace_payment_transactions')->where('id', $old->id)->update([
        'created_at' => now()->subDays(4),
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-reconcile-old-safe' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'paid', 'currency' => 'ETB', 'amount' => '449.00'],
        ], 200),
    ]);

    $this->artisan('chapa:reconcile')
        ->expectsOutputToContain('No pending Chapa transactions')
        ->assertSuccessful();
    expect($old->fresh()->status)->toBe('pending');

    $this->artisan('chapa:reconcile', ['--all' => true])
        ->expectsOutputToContain('Fulfilled successfully')
        ->assertSuccessful();

    expect($old->fresh()->status)->toBe('verified');
    expect(MarketplacePaymentReceipt::where('transaction_id', $old->id)->count())->toBe(1);
});

it('flags successful canceled Chapa charges for human settlement without changing records', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $canceled = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-canceled-paid-audit',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'canceled',
        'mode' => 'test',
        'provider' => 'chapa',
        'billing_interval' => 'monthly',
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-canceled-paid-audit' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'currency' => 'ETB',
                'amount' => 449,
                'tx_ref' => 'NF-canceled-paid-audit',
            ],
        ], 200),
    ]);

    $this->artisan('chapa:audit-canceled', ['--ref' => $canceled->tx_ref])
        ->expectsOutputToContain('PAID AFTER CANCELLATION')
        ->assertSuccessful();

    expect($canceled->fresh()->status)->toBe('canceled');
    expect($canceled->fresh()->verified_at)->toBeNull();
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
    expect(MarketplacePaymentReceipt::where('transaction_id', $canceled->id)->exists())->toBeFalse();
});
