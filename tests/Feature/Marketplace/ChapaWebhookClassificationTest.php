<?php

use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

it('never promotes refund, payout or unknown success webhooks into a Chapa purchase', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    // Deliberately include missing/misleading webhook_type fields: a signed
    // delivery is not evidence that the event belongs to a payment purchase.
    $cases = [
        ['payout.success', null],
        ['refund.success', null],
        ['charge.refunded', null],
        ['payment.partially_refunded', 'payment'],
        ['payment.fully_refunded', 'payment'],
        ['payment.success', 'refund'],
        ['charge.success', 'payout'],
        ['unrelated.success', 'payment'],
    ];

    Http::fake();

    foreach ($cases as $index => [$event, $type]) {
        $ref = 'NF-nonpayment-success-'.$index;
        $transaction = MarketplacePaymentTransaction::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'tx_ref' => $ref,
            'amount_etb' => 449,
            'currency' => 'ETB',
            'status' => 'initialized',
            'provider' => MarketplacePaymentTransaction::PROVIDER_CHAPA,
            'mode' => 'test',
            'billing_interval' => 'monthly',
        ]);

        $payload = [
            'event' => $event,
            'tx_ref' => $ref,
            'status' => 'success',
            'amount' => '449.00',
            'currency' => 'ETB',
        ];

        if ($type !== null) {
            $payload['webhook_type'] = $type;
        }

        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, config('chapa.webhookSecret'));

        $this->postJson('/webhooks/chapa', $payload, ['x-chapa-signature' => $signature])
            ->assertOk()
            ->assertJsonPath('message', 'Event ignored');

        expect($transaction->fresh()->status)->toBe('initialized')
            ->and($transaction->fresh()->verified_at)->toBeNull()
            ->and(ChapaWebhookEvent::where('tx_ref', $ref)->where('event', $event)->value('status'))->toBe('processed');
    }

    Http::assertNothingSent();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse()
        ->and(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('does not settle charge.success unless the event itself reports success', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-pending-charge-event',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'status' => 'initialized',
        'provider' => 'chapa',
        'mode' => 'test',
        'billing_interval' => 'monthly',
    ]);

    $payload = [
        'event' => 'charge.success',
        'tx_ref' => 'NF-pending-charge-event',
        'status' => 'pending',
    ];

    Http::fake();

    $this->postJson('/webhooks/chapa', $payload, [
        'x-chapa-signature' => hash_hmac('sha256', json_encode($payload), config('chapa.webhookSecret')),
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Event ignored');

    Http::assertNothingSent();
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});
