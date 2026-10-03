<?php

use App\Models\MarketplacePaymentReceipt;
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

it('creates exactly 1 subscription and 1 receipt when fulfilled twice', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_idempotency_test',
        'amount_etb' => 999,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 999, 'reference' => 'chapa_ref_123']];
    $service = app(MarketplaceFulfillmentService::class);

    $service->fulfill($tx, $verified);
    $service->fulfill($tx, $verified);

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('creates exactly 1 subscription and 1 receipt when fulfilled three times', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'pro')->first();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pro_idempotency_test_3x',
        'amount_etb' => 999,
        'currency' => 'ETB',
        'status' => 'initialized',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    $verified = ['status' => 'success', 'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 999, 'reference' => 'chapa_ref_123']];
    $service = app(MarketplaceFulfillmentService::class);

    $service->fulfill($tx, $verified);
    $service->fulfill($tx, $verified);
    $service->fulfill($tx, $verified);

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->count())->toBe(1);
});
