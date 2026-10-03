<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Services\MarketplaceFulfillmentService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 999, 'tx_ref' => 'test', 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);
});

it('grants 7 days for weekly billing_interval', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'weekly')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_duration_test',
        'amount_etb' => 349,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'weekly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 349, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    expect($sub->billing_interval)->toBe('weekly');
    expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe(7);
});

it('grants 30 days for monthly billing_interval', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_monthly_duration_test',
        'amount_etb' => 999,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 999, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    expect($sub->billing_interval)->toBe('monthly');
    expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe(30);
});

it('grants 365 days for yearly billing_interval', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_yearly_duration_test',
        'amount_etb' => 9990,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'yearly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 9990, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    expect($sub->billing_interval)->toBe('yearly');
    expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe(365);
});

it('reads billing_interval from the transaction, not from provider_response', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'weekly')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_no_meta_test',
        'amount_etb' => 349,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'weekly',
        'provider_response' => ['meta' => ['billing_interval' => 'yearly']], // wrong value in provider_response
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 349, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    expect($sub->billing_interval)->toBe('weekly');
    expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe(7);
});
