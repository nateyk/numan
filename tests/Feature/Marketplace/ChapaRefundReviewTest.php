<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentReview;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\MarketplaceFulfillmentService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/** @return array{0: User, 1: User, 2: MarketplacePaymentTransaction} */
function refundReviewFixture(): array
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
        'user_id' => $payer->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'tx_ref' => 'NF-REFUND-REVIEW-123',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'provider' => 'chapa',
        'status' => 'canceled',
        'mode' => 'test',
    ]);

    return [$payer->fresh(), $admin->fresh(), $transaction];
}

it('allows only superadmins to open a tracked Chapa refund review', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $route = route('admin.marketplace-payments.reviews.store', $transaction);

    $this->post($route, ['reason' => 'paid_after_cancellation'])
        ->assertRedirect(route('login'));
    $this->actingAs($payer)->post($route, ['reason' => 'paid_after_cancellation'])
        ->assertForbidden();

    $this->actingAs($admin)->post($route, ['reason' => 'paid_after_cancellation'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $review = MarketplacePaymentReview::firstOrFail();
    expect($review->reason)->toBe('paid_after_cancellation')
        ->and($review->status)->toBe('open')
        ->and($review->events()->count())->toBe(1);

    expect($transaction->fresh()->status)->toBe('canceled');
    expect(MarketplaceSubscription::where('workspace_id', $transaction->workspace_id)->exists())->toBeFalse();
});

it('is idempotent when an admin retries opening a review', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $route = route('admin.marketplace-payments.reviews.store', $transaction);

    $this->actingAs($admin)->post($route, ['reason' => 'refund_request']);
    $this->actingAs($admin)->post($route, ['reason' => 'refund_request']);

    expect(MarketplacePaymentReview::count())->toBe(1);
    expect(MarketplacePaymentReview::first()->events()->count())->toBe(1);
});

it('does not create review cases for manual transfers', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $transaction->update(['provider' => 'manual']);

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reviews.store', $transaction), [
            'reason' => 'refund_request',
        ])
        ->assertSessionHasErrors('reason');

    expect(MarketplacePaymentReview::count())->toBe(0);
});

it('requires provider evidence and never changes payment entitlements', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $this->actingAs($admin)->post(route('admin.marketplace-payments.reviews.store', $transaction), [
        'reason' => 'refund_request',
    ]);
    $review = MarketplacePaymentReview::firstOrFail();
    $route = route('admin.marketplace-payment-reviews.update', $review);

    $this->actingAs($admin)->patch($route, ['status' => 'provider_reported_refunded'])
        ->assertSessionHasErrors('status');
    $this->actingAs($admin)->patch($route, ['status' => 'investigating'])
        ->assertSessionHas('success');
    $this->actingAs($admin)->patch($route, ['status' => 'submitted_to_chapa'])
        ->assertSessionHas('success');
    $this->actingAs($admin)->patch($route, ['status' => 'provider_reported_refunded'])
        ->assertSessionHasErrors('provider_reference');

    $this->actingAs($admin)->patch($route, [
        'status' => 'provider_reported_refunded',
        'provider_reference' => 'REFTEST1234',
    ])->assertSessionHas('success');

    $this->actingAs($admin)->patch($route, [
        'status' => 'provider_reported_refunded',
        'provider_reference' => 'REFTEST1234',
    ])->assertSessionHas('success');

    expect($review->fresh()->status)->toBe('provider_reported_refunded')
        ->and($review->fresh()->provider_reference)->toBe('REFTEST1234')
        ->and($review->events()->count())->toBe(4)
        ->and($transaction->fresh()->status)->toBe('canceled')
        ->and($transaction->fresh()->verified_at)->toBeNull();

    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse();
    expect(MarketplaceSubscription::where('workspace_id', $transaction->workspace_id)->exists())->toBeFalse();
});

it('refuses an admin reviewing their own Chapa charge', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $transaction->update(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payments.reviews.store', $transaction), [
            'reason' => 'refund_request',
        ])
        ->assertSessionHasErrors('reason');

    expect(MarketplacePaymentReview::count())->toBe(0);
});

it('shows the review queue on the existing admin payment page', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $this->actingAs($admin)->post(route('admin.marketplace-payments.reviews.store', $transaction), [
        'reason' => 'refund_request',
    ]);

    $this->actingAs($admin)->get(route('admin.marketplace-payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/marketplace-payments')
            ->where('reviews.0.reason', 'refund_request')
            ->where('reviews.0.status', 'open'));
});

it('never grants subscriptions from signed refund notifications', function () {
    [$payer, $admin, $transaction] = refundReviewFixture();
    $transaction->update(['status' => 'initialized']);

    $payload = [
        'webhook_type' => 'refund',
        'event' => 'payment.fully_refunded',
        'status' => 'success',
        'merchant_reference' => $transaction->tx_ref,
        'amount' => '44900',
        'refunded_amount' => '44900',
        'customer' => ['email' => 'private@example.test'],
    ];
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, config('chapa.webhookSecret'));

    Http::fake();

    $this->postJson('/webhooks/chapa', $payload, [
        'x-chapa-signature' => $signature,
    ])->assertSuccessful();

    Http::assertNothingSent();
    expect($transaction->fresh()->status)->toBe('initialized');
    expect(MarketplaceSubscription::where('workspace_id', $transaction->workspace_id)->exists())->toBeFalse();
    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->exists())->toBeFalse();

    $event = \App\Models\ChapaWebhookEvent::where('tx_ref', $transaction->tx_ref)->firstOrFail();
    expect($event->status)->toBe('processed');
    expect($event->payload['webhook_type'])->toBe('refund');
    expect(json_encode($event->payload))->not->toContain('private@example.test');
});
