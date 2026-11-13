<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class ChapaCallbackController extends Controller
{
    public function __construct(
        protected ChapaService $chapa,
        protected MarketplaceFulfillmentService $fulfillment,
    ) {}

    public function show(Request $request, string $tx_ref)
    {
        $txRef = $tx_ref ?? $request->input('trx_ref') ?? $request->input('tx_ref');
        if (! $txRef) {
            $txRef = $this->chapa->getTransactionIdFromCallback($request);
        }

        if (! $txRef) {
            return redirect()->route('billing.index')->withErrors(['payment' => 'Missing transaction reference.']);
        }

        $transaction = MarketplacePaymentTransaction::where('tx_ref', $txRef)->first();
        if (! $transaction) {
            return redirect()->route('billing.index')->withErrors(['payment' => 'Transaction not found.']);
        }

        // Payment callbacks may only be inspected by the payer who started them.
        if ((int) $transaction->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        try {
            $verified = $this->chapa->verifyTransaction($txRef);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return redirect()->route('billing.index')->with('info', 'Payment verification is temporarily unavailable. Your payment reference is saved; check your plan status shortly.');
        }

        $data = $verified['data'] ?? $verified;
        $isSuccess = ($verified['status'] ?? null) === 'success' && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

        if ($isSuccess) {
            if (! $this->chapa->verifiedPaymentMatches($verified, $transaction)) {
                return redirect()->route('billing.index')->withErrors([
                    'payment' => 'Payment details could not be verified. Contact support.',
                ]);
            }

            $this->fulfillment->fulfill($transaction, $verified);

            return redirect()->route('billing.index')->with('success', 'Payment verified — plan activated.');
        }

        return redirect()->route('billing.index')->with('info', 'Payment is being verified. You will be updated shortly.');
    }
}
