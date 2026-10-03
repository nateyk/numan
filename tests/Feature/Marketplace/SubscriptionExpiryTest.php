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
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 349, 'tx_ref' => 'test', 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);
});

it('sets ends_at equal to current_period_ends_at when fulfilling a weekly subscription', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'weekly')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_abc123',
        'amount_etb' => 349,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'weekly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 349, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    expect($sub)->not->toBeNull();
    expect($sub->ends_at)->not->toBeNull();
    expect($sub->ends_at->toDateTimeString())->toBe($sub->current_period_ends_at->toDateTimeString());
    expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe(7);
});

it('stops being active after the weekly period has passed', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'weekly')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-weekly_expiry_test',
        'amount_etb' => 349,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'weekly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 349, 'reference' => 'chapa_ref_123']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    // Should be active now
    expect($workspace->activeMarketplaceSubscription())->not->toBeNull();

    // Simulate 8 days passing
    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    $sub->update([
        'current_period_start' => now()->subDays(8),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    // Should no longer be active
    expect($workspace->activeMarketplaceSubscription())->toBeNull();
});

it('stops being active after the yearly period has passed', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_yearly_expiry_test',
        'amount_etb' => 9990,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'yearly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 9990, 'reference' => 'chapa_ref_yearly']];
    app(MarketplaceFulfillmentService::class)->fulfill($tx, $verified);

    expect($workspace->activeMarketplaceSubscription())->not->toBeNull();

    // Simulate 366 days passing
    $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();
    $sub->update([
        'current_period_start' => now()->subDays(366),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    expect($workspace->activeMarketplaceSubscription())->toBeNull();
});
