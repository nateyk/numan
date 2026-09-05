<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceService;
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
            'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 999, 'tx_ref' => 'test', 'reference' => 'chapa_ref_123'],
        ], 200),
    ]);
});

it('requires authentication for checkout', function () {
    $this->postJson('/marketplace/checkout', ['plan' => 'pro', 'billing_interval' => 'monthly'])->assertUnauthorized();
});

it('creates chapa transaction and returns checkout_url for pro monthly', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    // Ensure profile for completeness
    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', ['plan' => 'pro', 'billing_interval' => 'monthly']);

    $response->assertSuccessful();
    $data = $response->json();
    expect($data['checkout_url'] ?? null)->toBe('https://checkout.chapa.co/test_123');

    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->exists())->toBeTrue();
    $tx = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->latest()->first();
    expect($tx->plan->slug)->toBe('pro');
    expect((float) $tx->amount_etb)->toBe(999.0);
    expect($tx->currency)->toBe('ETB');
});

it('handles free plan without chapa', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/marketplace/checkout', ['plan' => 'free', 'billing_interval' => 'monthly']);
    $response->assertSuccessful();
    // No transaction for free
    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('validates chapa webhook signature and processes', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'webhook_test_123',
        'amount_etb' => 999,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
    ]);

    $payload = json_encode(['event' => 'charge.success', 'tx_ref' => 'webhook_test_123', 'status' => 'success', 'amount' => 999, 'currency' => 'ETB']);
    $sig = hash_hmac('sha256', $payload, config('chapa.webhookSecret'));

    $response = $this->postJson('/webhooks/chapa', json_decode($payload, true), ['x-chapa-signature' => $sig]);

    // Should process (200) even if verify mocked
    $response->assertSuccessful();
});
