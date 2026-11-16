<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaSubscriptionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class ChapaCallbackController extends Controller
{
    public function __construct(
        protected ChapaSubscriptionVerifier $verifier,
    ) {}

    /**
     * The browser return URL is authenticated and shows private status only
     * to the payer. Chapa's unauthenticated server callback uses its own route.
     */
    public function show(Request $request, string $tx_ref)
    {
        $transaction = MarketplacePaymentTransaction::where('tx_ref', $tx_ref)->firstOrFail();

        if (! $transaction->isChapa() || (int) $transaction->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        try {
            $status = $this->verifier->verify($transaction);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return redirect()->route('billing.index')->with(
                'info',
                'Payment verification is temporarily unavailable. Your reference is saved; check your payment status shortly.',
            );
        }

        return match ($status) {
            'verified' => redirect()->route('billing.index')
                ->with('success', 'Payment verified — your subscription is active.'),
            'mismatch' => redirect()->route('billing.index')
                ->withErrors(['payment' => 'The provider details did not match this subscription. Contact support.']),
            'canceled' => redirect()->route('billing.index')
                ->withErrors(['payment' => 'This payment attempt was canceled. If you were charged, contact support for settlement review.']),
            default => redirect()->route('billing.index')
                ->with('info', 'Payment confirmation is pending. Check your payment status shortly.'),
        };
    }
}
