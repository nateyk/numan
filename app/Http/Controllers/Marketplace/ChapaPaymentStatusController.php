<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaSubscriptionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapaPaymentStatusController extends Controller
{
    public function __construct(
        protected ChapaSubscriptionVerifier $verifier,
    ) {}

    /**
     * Explicit, authenticated retry of an indeterminate hosted checkout.
     * No new charge is initialized; the original reference is verified.
     */
    public function verify(Request $request, string $tx_ref): JsonResponse
    {
        $transaction = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
            ->where('tx_ref', $tx_ref)
            ->firstOrFail();

        if ((int) $transaction->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        try {
            $status = $this->verifier->verify($transaction);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return response()->json([
                'status' => 'pending',
                'retryable' => true,
                'message' => 'Chapa is temporarily unavailable. Keep this reference and check again later.',
            ], 503);
        }

        return match ($status) {
            'verified' => response()->json([
                'status' => 'verified',
                'message' => 'Payment verified. Your subscription has been activated.',
            ]),
            'mismatch' => response()->json([
                'status' => 'mismatch',
                'message' => 'The payment details need support review. Do not pay again yet.',
            ], 409),
            'canceled' => response()->json([
                'status' => 'canceled',
                'message' => 'This payment attempt is canceled. Contact support if money was collected.',
            ], 409),
            default => response()->json([
                'status' => 'pending',
                'message' => 'No confirmed payment yet. If you paid, check again later before starting a new checkout.',
            ]),
        };
    }
}
