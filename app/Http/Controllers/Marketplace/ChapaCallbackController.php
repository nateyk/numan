<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use App\Services\MarketplaceFulfillmentService;
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

        $verified = $this->chapa->verifyTransaction($txRef);

        $data = $verified['data'] ?? $verified;
        $isSuccess = ($verified['status'] ?? null) === 'success' && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

        if ($isSuccess) {
            $expectedAmount = (string) $transaction->amount_etb;
            $returnedAmount = (string) ($data['amount'] ?? $data['amount_etb'] ?? '');
            $currency = $data['currency'] ?? 'ETB';

            if ($currency !== 'ETB' || ($returnedAmount !== '' && $returnedAmount !== $expectedAmount && (float) $returnedAmount !== (float) $expectedAmount)) {
                return redirect()->route('billing.index')->withErrors(['payment' => 'Amount mismatch. Contact support.']);
            }

            $this->fulfillment->fulfill($transaction, $verified);

            return redirect()->route('billing.index')->with('success', 'Payment verified — plan activated.');
        }

        return redirect()->route('billing.index')->with('info', 'Payment is being verified. You will be updated shortly.');
    }
}
