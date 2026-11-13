<?php

use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function verificationTransaction(): MarketplacePaymentTransaction
{
    return new MarketplacePaymentTransaction([
        'tx_ref' => 'NF-single-profile-payment',
        'amount_etb' => '449.00',
        'currency' => 'ETB',
    ]);
}

function verifiedChapaPayload(array $changes = []): array
{
    return [
        'status' => 'success',
        'data' => array_merge([
            'status' => 'paid',
            'currency' => 'ETB',
            'amount' => '449.00',
            'tx_ref' => 'NF-single-profile-payment',
        ], $changes),
    ];
}

it('accepts an exact decimal amount in valid Chapa verification', function () {
    $service = app(ChapaService::class);

    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['amount' => 449]), verificationTransaction()))->toBeTrue();
    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['amount' => '449.0']), verificationTransaction()))->toBeTrue();
});

it('fails closed when the verified payment amount or currency is absent', function () {
    $service = app(ChapaService::class);
    $withoutAmount = verifiedChapaPayload();
    unset($withoutAmount['data']['amount']);

    $withoutCurrency = verifiedChapaPayload();
    unset($withoutCurrency['data']['currency']);

    expect($service->verifiedPaymentMatches($withoutAmount, verificationTransaction()))->toBeFalse();
    expect($service->verifiedPaymentMatches($withoutCurrency, verificationTransaction()))->toBeFalse();
});

it('rejects incorrect amounts, currencies, references and incomplete payment status', function () {
    $service = app(ChapaService::class);
    $transaction = verificationTransaction();

    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['amount' => '448.99']), $transaction))->toBeFalse();
    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['amount' => '449.001']), $transaction))->toBeFalse();
    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['currency' => 'USD']), $transaction))->toBeFalse();
    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['tx_ref' => 'someone-else']), $transaction))->toBeFalse();
    expect($service->verifiedPaymentMatches(verifiedChapaPayload(['status' => 'pending']), $transaction))->toBeFalse();
});

it('rejects a forged webhook when the signing secret is not configured', function () {
    $body = json_encode(['event' => 'charge.success', 'tx_ref' => 'NF-forged']);
    $request = Request::create('/webhooks/chapa', 'POST', [], [], [], [], $body);
    $request->headers->set('x-chapa-signature', hash_hmac('sha256', $body, ''));

    $service = new ChapaService(webhookSecret: '');

    expect($service->validateWebhook($request))->toBeFalse();
});

it('treats a Chapa server error during initialization as a retryable provider outage', function () {
    Http::fake([
        'api.chapa.co/v1/transaction/initialize' => Http::response(['message' => 'Temporarily unavailable'], 503),
    ]);

    expect(fn () => app(ChapaService::class)->initializePayment(['tx_ref' => 'NF-retry-503']))
        ->toThrow(RequestException::class);
});

it('treats a Chapa server error during verification as a retryable provider outage', function () {
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response(['message' => 'Temporarily unavailable'], 503),
    ]);

    expect(fn () => app(ChapaService::class)->verifyTransaction('NF-retry-503'))
        ->toThrow(RequestException::class);
});
