<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\ChapaService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

function subscriptionChapaAttempt(string $reference = 'NF-subscription-callback-test'): array
{
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => $reference,
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => MarketplacePaymentTransaction::PROVIDER_CHAPA,
    ]);

    return [$user, $workspace, $transaction];
}

function fakeSuccessfulChapaSubscription(string $reference, string $mode = 'test'): void
{
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'tx_ref' => $reference,
                'reference' => 'chapa-'.$reference,
                'currency' => 'ETB',
                'amount' => '449.00',
                'mode' => $mode,
            ],
        ], 200),
    ]);
}

it('provides separate public provider and private browser return URLs', function () {
    [$user] = marketplaceTestUserWithWorkspace();

    Http::fake([
        'api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/test-subscription'],
        ], 200),
    ]);

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ])->assertSuccessful();

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    Http::assertSent(function ($request) use ($transaction) {
        $body = $request->data();

        return $body['tx_ref'] === $transaction->tx_ref
            && $body['callback_url'] === route('chapa.server-callback', ['tx_ref' => $transaction->tx_ref])
            && $body['return_url'] === route('chapa.callback', ['tx_ref' => $transaction->tx_ref])
            && $body['callback_url'] !== $body['return_url'];
    });
});

it('fulfills once from the provider callback with no authenticated browser session', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt();
    fakeSuccessfulChapaSubscription($transaction->tx_ref);

    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();
    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();

    expect($transaction->fresh()->status)->toBe('verified')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1)
        ->and($transaction->fresh()->receipt)->not->toBeNull();
});

it('does not activate an unpaid subscription just because a public callback arrived', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-callback-unpaid');

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'failed',
            'data' => ['status' => 'pending'],
        ], 200),
    ]);

    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();

    expect($transaction->fresh()->status)->toBe('initialized')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('rejects a test-live mode mismatch across the provider callback', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-callback-live-mismatch');
    fakeSuccessfulChapaSubscription($transaction->tx_ref, 'live');

    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertStatus(422);

    expect($transaction->fresh()->verified_at)->toBeNull()
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('does not verify or reactivate a canceled checkout through the provider callback', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-callback-canceled');
    $transaction->update(['status' => 'canceled']);
    Http::fake();

    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe('canceled')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('lets only the original payer check and settle their existing checkout', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-payer-status-check');
    $otherUser = User::factory()->create();
    fakeSuccessfulChapaSubscription($transaction->tx_ref);

    $url = '/marketplace/payments/'.$transaction->tx_ref.'/verify';

    $this->postJson($url)->assertUnauthorized();
    $this->actingAs($otherUser)->postJson($url)->assertNotFound();
    $this->actingAs($user)->postJson($url)
        ->assertSuccessful()
        ->assertJsonPath('status', 'verified');

    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1);
});

it('never tells a payer that a canceled subscription was activated', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-canceled-browser-return');
    $transaction->update(['status' => 'canceled']);
    Http::fake();

    $this->actingAs($user)
        ->get('/chapa/callback/'.$transaction->tx_ref)
        ->assertRedirect(route('billing.index'))
        ->assertSessionHasErrors('payment');

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe('canceled')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('requires a receipt before reporting a previously verified payment as complete', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-verified-without-receipt');
    $transaction->update(['status' => 'verified', 'verified_at' => now()]);
    Http::fake();

    $this->actingAs($user)
        ->postJson('/marketplace/payments/'.$transaction->tx_ref.'/verify')
        ->assertStatus(409)
        ->assertJsonPath('status', 'mismatch');

    Http::assertNothingSent();
    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse();
});

it('keeps a subscription pending when the payer status recheck cannot reach Chapa', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-recheck-provider-outage');

    $this->app->instance(ChapaService::class, new class extends ChapaService
    {
        public function verifyTransaction(string $txRef): array
        {
            throw new ConnectionException('Connection timed out');
        }
    });

    $this->actingAs($user)
        ->postJson('/marketplace/payments/'.$transaction->tx_ref.'/verify')
        ->assertStatus(503)
        ->assertJsonPath('retryable', true);

    expect($transaction->fresh()->status)->toBe('initialized')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('returns the saved transaction reference after a checkout initialization timeout', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $this->mock(ChapaService::class, function ($mock): void {
        $mock->shouldReceive('generateReference')->once()->andReturn('NF-checkout-recoverable');
        $mock->shouldReceive('initializePayment')->once()->andThrow(
            new ConnectionException('Gateway unavailable'),
        );
    });

    $this->actingAs($user)->postJson('/marketplace/checkout', [
        'plan' => 'standard',
        'billing_interval' => 'monthly',
    ])
        ->assertStatus(503)
        ->assertJsonPath('retryable', true)
        ->assertJsonPath('tx_ref', 'NF-checkout-recoverable');

    expect(MarketplacePaymentTransaction::where('tx_ref', 'NF-checkout-recoverable')
        ->value('status'))->toBe('pending');
});

it('restores uninterrupted freight access after a verified Chapa grace-period renewal', function () {
    [$user, $workspace, $transaction] = subscriptionChapaAttempt('NF-chapa-grace-renewal');
    $paidUntil = now()->addDays(5);
    $previous = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $transaction->plan_id,
        'user_id' => $user->id,
        'status' => 'canceled',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(25),
        'current_period_ends_at' => $paidUntil,
        'ends_at' => $paidUntil,
        'provider' => 'chapa',
    ]);

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'pending', 'tx_ref' => $transaction->tx_ref],
        ], 200),
    ]);

    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();
    expect($transaction->fresh()->verified_at)->toBeNull()
        ->and($previous->fresh()->status)->toBe('canceled')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);

    fakeSuccessfulChapaSubscription($transaction->tx_ref);
    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();
    $this->get('/chapa/notify/'.$transaction->tx_ref)->assertSuccessful();

    $renewed = MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->firstOrFail();

    expect($transaction->fresh()->status)->toBe('verified')
        ->and($previous->fresh()->status)->toBe('superseded')
        ->and($renewed->current_period_start->equalTo($paidUntil))->toBeTrue()
        ->and($renewed->current_period_ends_at->equalTo($paidUntil->copy()->addDays(30)))->toBeTrue()
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1);

    $this->actingAs($user)->get('/freight')
        ->assertOk()->assertInertia(fn ($page) => $page->component('discover/index'));
});
