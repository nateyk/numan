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
});

function testRenewalTransaction(int $workspaceId, int $userId, string $ref): MarketplacePaymentTransaction
{
    return MarketplacePaymentTransaction::create([
        'workspace_id' => $workspaceId,
        'user_id' => $userId,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'tx_ref' => $ref,
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'provider' => 'chapa',
        'status' => 'initialized',
        'mode' => 'test',
    ]);
}

function testRenewalVerification(string $ref): array
{
    return [
        'status' => 'success',
        'data' => [
            'tx_ref' => $ref,
            'status' => 'paid',
            'currency' => 'ETB',
            'amount' => '449.00',
            'mode' => 'test',
        ],
    ];
}

it('refuses provider-backed acceptance when the configuration is not Test Mode', function () {
    config()->set('chapa.mode', 'live');
    config()->set('chapa.secretKey', 'CHASECK_TEST-example');

    $this->artisan('chapa:accept-renewal', [
        'initial' => 'NF-initial',
        'renewal' => 'NF-renewal',
    ])->expectsOutputToContain('requires CHAPA_MODE=test')
        ->assertFailed();
});

it('refuses mismatched or missing acceptance references without calling Chapa', function () {
    config()->set('chapa.mode', 'test');
    config()->set('chapa.secretKey', 'CHASECK_TEST-example');
    config()->set('chapa.baseUrl', 'https://api.chapa.co/v1');

    Http::fake();

    $this->artisan('chapa:accept-renewal', [
        'initial' => 'NF-missing-first',
        'renewal' => 'NF-missing-second',
    ])->expectsOutputToContain('two different existing Numan payment references')
        ->assertFailed();

    Http::assertNothingSent();
});

it('validates two provider-backed paid periods, receipts and continuity without writing records', function () {
    config()->set('chapa.mode', 'test');
    config()->set('chapa.secretKey', 'CHASECK_TEST-example');
    config()->set('chapa.baseUrl', 'https://api.chapa.co/v1');

    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $first = testRenewalTransaction($workspace->id, $user->id, 'NF-accept-first');
    $second = testRenewalTransaction($workspace->id, $user->id, 'NF-accept-renewal');

    $fulfillment = app(MarketplaceFulfillmentService::class);
    $fulfillment->fulfill($first, testRenewalVerification($first->tx_ref));
    $fulfillment->fulfill($second, testRenewalVerification($second->tx_ref));

    $before = MarketplaceSubscription::where('workspace_id', $workspace->id)->count();

    Http::fake([
        'api.chapa.co/v1/transaction/verify/NF-accept-first' => Http::response(
            testRenewalVerification($first->tx_ref), 200,
        ),
        'api.chapa.co/v1/transaction/verify/NF-accept-renewal' => Http::response(
            testRenewalVerification($second->tx_ref), 200,
        ),
    ]);

    $this->artisan('chapa:accept-renewal', [
        'initial' => $first->tx_ref,
        'renewal' => $second->tx_ref,
    ])
        ->expectsOutputToContain('Both distinct checkouts were verified directly')
        ->assertSuccessful();

    Http::assertSentCount(2);
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe($before);
    expect($first->fresh()->status)->toBe('verified');
    expect($second->fresh()->status)->toBe('verified');
});

it('fails acceptance when the provider cannot verify one of the recorded payments', function () {
    config()->set('chapa.mode', 'test');
    config()->set('chapa.secretKey', 'CHASECK_TEST-example');
    config()->set('chapa.baseUrl', 'https://api.chapa.co/v1');

    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $first = testRenewalTransaction($workspace->id, $user->id, 'NF-accept-pending-a');
    $second = testRenewalTransaction($workspace->id, $user->id, 'NF-accept-pending-b');

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'pending'],
        ]),
    ]);

    $this->artisan('chapa:accept-renewal', [
        'initial' => $first->tx_ref,
        'renewal' => $second->tx_ref,
    ])->expectsOutputToContain('Provider verification did not match')
        ->assertFailed();
});
