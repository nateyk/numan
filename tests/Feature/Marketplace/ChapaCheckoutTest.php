<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\ChapaService;
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

it('returns a json error a fetch client can read when chapa initialization fails', function () {
    [$user] = marketplaceTestUserWithWorkspace();

    // Mirror production: Chapa answers 200 with a failed status (bad key,
    // inactive business account). The plans page posts with fetch, which
    // follows redirects, so a redirect-with-errors here would arrive as HTML
    // and crash the checkout handler. JSON keeps the message readable.
    $this->mock(ChapaService::class, function ($mock): void {
        $mock->shouldReceive('generateReference')->andReturn('NF-test-failed-init');
        $mock->shouldReceive('initializePayment')->andReturn([
            'status' => 'failed',
            'message' => 'Invalid API Key.',
            'data' => null,
        ]);
    });

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ]);

    $response->assertStatus(422)->assertJsonPath('error', 'Invalid API Key.');
});

it('flattens chapa field errors into a readable string', function () {
    [$user] = marketplaceTestUserWithWorkspace();

    // Chapa field errors arrive as an object ({field: [messages]}), which
    // React cannot render. The endpoint must flatten them to a string.
    $this->mock(ChapaService::class, function ($mock): void {
        $mock->shouldReceive('generateReference')->andReturn('NF-test-field-errors');
        $mock->shouldReceive('initializePayment')->andReturn([
            'status' => 'failed',
            'message' => [
                'tx_ref' => ['The tx ref must not exceed 50 characters.'],
                'customization.description' => ['The customization.description may only contain letters.'],
            ],
            'data' => null,
        ]);
    });

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ]);

    $response->assertStatus(422);
    $error = $response->json('error');
    expect($error)->toBeString()
        ->and($error)->toContain('tx ref must not exceed 50 characters')
        ->and($error)->toContain('customization.description may only contain letters');
});

it('generates references within the chapa 50 character limit', function () {
    $service = app(ChapaService::class);

    foreach (['weekly', 'monthly', 'yearly'] as $interval) {
        $ref = $service->generateReference('NF-standard-'.$interval);

        expect(strlen($ref))->toBeLessThanOrEqual(50)
            ->and($ref)->toMatch('/^[A-Za-z0-9_-]+$/');
    }
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

it('does not activate a paid plan from Chapa verification missing an amount', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-verified-no-amount',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
    ]);

    // Keep ChapaService's real, initialized webhook secret and signature
    // check while overriding only the remote verification response.
    $this->app->instance(ChapaService::class, new class extends ChapaService
    {
        public function verifyTransaction(string $txRef): array
        {
            return [
                'status' => 'success',
                'data' => ['status' => 'paid', 'currency' => 'ETB', 'reference' => 'chapa-ref'],
            ];
        }
    });

    $payload = json_encode([
        'event' => 'charge.success',
        'tx_ref' => 'NF-verified-no-amount',
        'status' => 'success',
    ]);
    $signature = hash_hmac('sha256', $payload, config('chapa.webhookSecret'));

    $this->postJson('/webhooks/chapa', json_decode($payload, true), ['x-chapa-signature' => $signature])
        ->assertSuccessful();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect(MarketplacePaymentTransaction::where('tx_ref', 'NF-verified-no-amount')->value('verified_at'))->toBeNull();
});

it('refuses a freight plan checkout by a profile without billing permission', function () {
    [$owner, $workspace] = marketplaceTestUserWithWorkspace();
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ])->assertForbidden();

    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('keeps the payment attempt pending when Chapa checkout times out', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $this->mock(ChapaService::class, function ($mock): void {
        $mock->shouldReceive('generateReference')->andReturn('NF-timeout-preserve-ref');
        $mock->shouldReceive('initializePayment')->andThrow(
            new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
        );
    });

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ])->assertStatus(503)
        ->assertJsonPath('retryable', true);

    $attempt = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->status)->toBe('pending')
        ->and($attempt->verified_at)->toBeNull();
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('marks a rejected checkout initialization failed instead of initialized', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $this->mock(ChapaService::class, function ($mock): void {
        $mock->shouldReceive('generateReference')->andReturn('NF-rejected-init');
        $mock->shouldReceive('initializePayment')->andReturn([
            'status' => 'failed',
            'message' => 'Provider unavailable.',
        ]);
    });

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'weekly',
    ])->assertStatus(422);

    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->value('status'))->toBe('failed');
});

it('requests a webhook retry when provider verification is unavailable', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-webhook-verification-outage',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    $this->app->instance(ChapaService::class, new class extends ChapaService
    {
        public function verifyTransaction(string $txRef): array
        {
            throw new \Illuminate\Http\Client\ConnectionException('Provider timed out');
        }
    });

    $payload = json_encode([
        'event' => 'charge.success',
        'tx_ref' => $transaction->tx_ref,
        'status' => 'success',
    ]);
    $signature = hash_hmac('sha256', $payload, config('chapa.webhookSecret'));

    $this->postJson('/webhooks/chapa', json_decode($payload, true), [
        'x-chapa-signature' => $signature,
    ])->assertStatus(503);

    expect($transaction->fresh()->status)->toBe('initialized');
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});
