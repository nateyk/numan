<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaSubscriptionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class ChapaServerCallbackController extends Controller
{
    public function __construct(
        protected ChapaSubscriptionVerifier $verifier,
    ) {}

    /**
     * Chapa calls callback_url directly, without the customer's session.
     * The remote provider verification, not this unsigned GET request, is
     * the sole evidence required to grant a subscription.
     *
     * Never return payer/plan details from this public endpoint.
     */
    public function handle(string $tx_ref): JsonResponse
    {
        $transaction = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
            ->where('tx_ref', $tx_ref)
            ->first();

        if (! $transaction) {
            return response()->json(['message' => 'Unknown payment reference'], 404);
        }

        try {
            $result = $this->verifier->verify($transaction);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            // Chapa retries non-2xx callbacks. Reconciliation remains
            // available if callbacks or webhooks are not delivered.
            return response()->json(['message' => 'Temporary verification failure'], 503);
        }

        if ($result === 'mismatch') {
            return response()->json(['message' => 'Payment requires review'], 422);
        }

        // Acknowledge pending/canceled attempts without granting access.
        // No payer or subscription information is disclosed publicly.
        return response()->json(['message' => 'Callback received']);
    }
}
