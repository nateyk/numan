<?php

use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

it('minimizes Chapa initialization and verification data at the database write boundary', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-provider-data-minimized',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => 'chapa',
        'provider_response' => [
            'status' => 'success',
            'message' => 'Secret payer: contact@example.test',
            'email' => 'contact@example.test',
            'first_name' => 'SecretPayerName',
            'data' => [
                'status' => 'paid',
                'amount' => '449.00',
                'currency' => 'ETB',
                'tx_ref' => 'NF-provider-data-minimized',
                'checkout_url' => 'https://checkout.chapa.co/private_token_value',
                'customer' => ['phone' => '+251911000777'],
                'metadata' => ['private' => 'SecretPayerName'],
            ],
        ],
    ]);

    $stored = DB::table('marketplace_payment_transactions')
        ->where('id', $transaction->id)
        ->value('provider_response');

    expect($stored)->not->toContain('contact@example.test')
        ->not->toContain('SecretPayerName')
        ->not->toContain('+251911000777')
        ->not->toContain('private_token_value');

    expect($transaction->fresh()->provider_response)->toMatchArray([
        '_schema' => 'numan.chapa.payment-summary/1',
        'status' => 'success',
        'data' => [
            'status' => 'paid',
            'amount' => '449.00',
            'currency' => 'ETB',
            'tx_ref' => 'NF-provider-data-minimized',
        ],
    ]);
});

it('stores a verified webhook body hash and no payer identity or nested metadata', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-webhook-minimize-test',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    $payload = [
        'event' => 'charge.success',
        'tx_ref' => 'NF-webhook-minimize-test',
        'status' => 'success',
        'first_name' => 'PrivateFirstName',
        'last_name' => 'PrivateLastName',
        'email' => 'private@example.test',
        'phone_number' => '+251911009999',
        'data' => [
            'status' => 'success',
            'amount' => 449,
            'currency' => 'ETB',
            'tx_ref' => 'NF-webhook-minimize-test',
            'customer' => ['email' => 'nested-private@example.test'],
        ],
    ];

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'amount' => 449,
                'currency' => 'ETB',
                'tx_ref' => 'NF-webhook-minimize-test',
                'first_name' => 'PrivateFirstName',
                'email' => 'private@example.test',
                'phone_number' => '+251911009999',
            ],
        ], 200),
    ]);

    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, config('chapa.webhookSecret'));

    $this->postJson('/webhooks/chapa', $payload, ['x-chapa-signature' => $signature])
        ->assertSuccessful();

    $event = ChapaWebhookEvent::where('tx_ref', 'NF-webhook-minimize-test')->firstOrFail();

    expect($event->payload_hash)->toBe(hash('sha256', $raw));
    expect($event->payload['event'])->toBe('charge.success');
    expect($event->payload['data']['amount'])->toBe(449);
    expect($event->payload)->not->toHaveKey('email');

    $storedPayload = DB::table('chapa_webhook_events')
        ->where('id', $event->id)
        ->value('payload');

    $storedVerification = DB::table('marketplace_payment_transactions')
        ->where('tx_ref', 'NF-webhook-minimize-test')
        ->value('provider_response');

    foreach ([$storedPayload, $storedVerification] as $stored) {
        expect($stored)->not->toContain('PrivateFirstName')
            ->not->toContain('PrivateLastName')
            ->not->toContain('private@example.test')
            ->not->toContain('nested-private@example.test')
            ->not->toContain('+251911009999');
    }
});

it('rejects nested objects posing as financial fields instead of persisting them', function () {
    $webhook = ChapaWebhookEvent::create([
        'event' => 'charge.pending',
        'tx_ref' => 'NF-nested-data',
        'payload' => [
            'event' => 'charge.pending',
            'status' => ['email' => 'private@example.test'],
            'data' => [
                'amount' => ['secret' => 'private@example.test'],
                'reference' => 'safe-reference',
                'customer' => ['phone' => '+251900000000'],
            ],
        ],
        'status' => 'pending',
    ]);

    expect($webhook->fresh()->payload)->toBe([
        'event' => 'charge.pending',
        'data' => ['reference' => 'safe-reference'],
        '_schema' => 'numan.chapa.payment-summary/1',
    ]);
});
