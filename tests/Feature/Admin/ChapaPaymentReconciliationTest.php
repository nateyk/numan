<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/** @return array{0: User, 1: MarketplacePaymentTransaction} */
function chapaAdminReconciliationFixture(string $provider = 'chapa', string $status = 'initialized'): array
{
    [$payer, $workspace] = marketplaceTestUserWithWorkspace();

    $admin = User::factory()->create([
        'is_superadmin' => true,
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);
    $adminWorkspace = app(WorkspaceService::class)->createPersonalWorkspace($admin);
    $admin->update(['current_workspace_id' => $adminWorkspace->id]);

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-admin-settlement-test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => $status,
        'mode' => 'test',
        'provider' => $provider,
    ]);

    return [$admin->fresh(), $transaction];
}

it('admin Chapa verification commits one subscription and receipt before reporting success', function () {
    [$admin, $transaction] = chapaAdminReconciliationFixture();

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'tx_ref' => $transaction->tx_ref,
                'amount' => '449.00',
                'currency' => 'ETB',
                'mode' => 'test',
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaction->fresh()->status)->toBe('verified')
        ->and($transaction->fresh()->verified_at)->not->toBeNull()
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1)
        ->and(MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->count())->toBe(1);
});

it('admin reconciliation never sends manual payment references to Chapa', function () {
    [$admin, $transaction] = chapaAdminReconciliationFixture('manual', 'pending');

    Http::fake();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertSessionHasErrors('payment');

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe('pending')
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse();
});

it('admin reconciliation does not claim paid access from an amount mismatch', function () {
    [$admin, $transaction] = chapaAdminReconciliationFixture();

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'tx_ref' => $transaction->tx_ref,
                'amount' => '199.00',
                'currency' => 'ETB',
                'mode' => 'test',
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertSessionHasErrors('payment');

    expect($transaction->fresh()->status)->toBe('initialized')
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse()
        ->and(MarketplaceSubscription::where('workspace_id', $transaction->workspace_id)->exists())->toBeFalse();
});

it('admin reconciliation cannot reactivate a canceled checkout', function () {
    [$admin, $transaction] = chapaAdminReconciliationFixture('chapa', 'canceled');

    Http::fake();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertSessionHasErrors('payment');

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe('canceled')
        ->and(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse();
});

it('non-admins cannot use the Chapa admin reconciliation endpoint', function () {
    [$admin, $transaction] = chapaAdminReconciliationFixture();

    $this->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertRedirect(route('login'));

    $payer = $transaction->user;
    $this->actingAs($payer)
        ->post(route('admin.marketplace-payments.reconcile', $transaction))
        ->assertForbidden();
});
