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
        'api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/test_123'],
        ], 200),
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 449, 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);
});

it('requires authentication for checkout', function () {
    $this->postJson('/marketplace/checkout', ['plan' => 'standard', 'billing_interval' => 'monthly'])->assertUnauthorized();
});

it('creates a chapa transaction at the monthly price', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ]);

    $response->assertSuccessful();
    expect($response->json()['checkout_url'] ?? null)->toBe('https://checkout.chapa.co/test_123');

    $tx = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->latest()->first();
    expect($tx->plan->slug)->toBe('standard');
    expect((float) $tx->amount_etb)->toBe(449.0);
    expect($tx->currency)->toBe('ETB');
    expect($tx->billing_interval)->toBe('monthly');
});

it('creates a chapa transaction at the weekly price', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'weekly',
    ])->assertSuccessful();

    $tx = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->latest()->first();
    expect((float) $tx->amount_etb)->toBe(149.0);
    expect($tx->billing_interval)->toBe('weekly');
});

it('creates a chapa transaction at the yearly price', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'yearly',
    ])->assertSuccessful();

    $tx = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->latest()->first();
    expect((float) $tx->amount_etb)->toBe(3999.0);
    expect($tx->billing_interval)->toBe('yearly');
});

it('refuses a zero-priced checkout and leaves an active subscription untouched', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    // Active paid subscription the user must keep.
    $sub = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    // A zero-priced duration must not be purchasable.
    $plan->update(['price_weekly_etb' => 0]);

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'weekly',
    ]);

    $response->assertStatus(422);
    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->count())->toBe(0);

    $sub->refresh();
    expect($sub->status)->toBe('active');
    expect($sub->ends_at)->not->toBeNull();
});

it('validates the chapa webhook signature and processes', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'webhook_test_123',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
    ]);

    $payload = json_encode([
        'event' => 'charge.success',
        'tx_ref' => 'webhook_test_123',
        'status' => 'success',
        'amount' => 449,
        'currency' => 'ETB',
    ]);
    $signature = hash_hmac('sha256', $payload, config('chapa.webhookSecret'));

    $this->postJson('/webhooks/chapa', json_decode($payload, true), ['x-chapa-signature' => $signature])
        ->assertSuccessful();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('fulfilling all three durations gives 7, 30 and 365 day periods', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();
    $service = app(MarketplaceFulfillmentService::class);

    $cases = [
        'weekly' => [149, 7],
        'monthly' => [449, 30],
        'yearly' => [3999, 365],
    ];

    foreach ($cases as $interval => [$amount, $days]) {
        $tx = MarketplacePaymentTransaction::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'tx_ref' => "NF-standard_duration_{$interval}",
            'amount_etb' => $amount,
            'currency' => 'ETB',
            'billing_interval' => $interval,
            'status' => 'initialized',
            'mode' => 'test',
        ]);

        $service->fulfill($tx, [
            'status' => 'success',
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => $amount, 'reference' => 'ref_'.$interval],
        ]);

        $sub = MarketplaceSubscription::where('chapa_tx_ref', "NF-standard_duration_{$interval}")->first();

        expect($sub)->not->toBeNull();
        expect((int) $sub->current_period_start->diffInDays($sub->current_period_ends_at))->toBe($days);
        expect((int) $sub->ends_at->diffInSeconds($sub->current_period_ends_at, false))->toBe(0);
    }
});
