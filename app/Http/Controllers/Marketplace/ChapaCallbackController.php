<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Services\ChapaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChapaCallbackController extends Controller
{
    public function __construct(protected ChapaService $chapa) {}

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

        // Chapa verify returns status success and data with status success, amount, currency
        $data = $verified['data'] ?? $verified;
        $isSuccess = ($verified['status'] ?? null) === 'success' && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

        if ($isSuccess) {
            // Validate amount/currency
            $expectedAmount = (string) $transaction->amount_etb;
            $returnedAmount = (string) ($data['amount'] ?? $data['amount_etb'] ?? '');
            $currency = $data['currency'] ?? 'ETB';

            if ($currency !== 'ETB' || ($returnedAmount !== '' && $returnedAmount !== $expectedAmount && (float) $returnedAmount !== (float) $expectedAmount)) {
                Log::warning('Chapa callback amount mismatch', ['tx_ref' => $txRef, 'expected' => $expectedAmount, 'got' => $returnedAmount, 'currency' => $currency]);

                return redirect()->route('billing.index')->withErrors(['payment' => 'Amount mismatch. Contact support.']);
            }

            $this->fulfill($transaction, $verified);

            return redirect()->route('billing.index')->with('success', 'Payment verified — plan activated.');
        }

        // Not success yet — webhook may still arrive
        Log::info('Chapa callback not yet success', ['tx_ref' => $txRef, 'verified' => $verified]);

        return redirect()->route('billing.index')->with('info', 'Payment is being verified. You will be updated shortly.');
    }

    protected function fulfill(MarketplacePaymentTransaction $transaction, array $verified): void
    {
        if ($transaction->status === 'verified' && $transaction->verified_at) {
            return; // idempotent
        }

        DB::transaction(function () use ($transaction, $verified) {
            $data = $verified['data'] ?? $verified;
            $chapaRef = $data['reference'] ?? $data['chapa_ref'] ?? $data['tx_ref'] ?? null;

            $transaction->update([
                'status' => 'verified',
                'verified_at' => now(),
                'chapa_ref' => $chapaRef ?? $transaction->chapa_ref,
                'provider_response' => $verified,
            ]);

            $workspace = $transaction->workspace;
            $plan = $transaction->plan;
            if (! $workspace || ! $plan) {
                return;
            }

            $billingInterval = $transaction->provider_response['meta']['billing_interval'] ?? $verified['meta']['billing_interval'] ?? $transaction->provider_response['billing_interval'] ?? 'monthly';
            // fallback to meta from transaction
            if (! in_array($billingInterval, ['monthly', 'yearly'])) {
                $billingInterval = 'monthly';
            }

            // Cancel previous active subs
            MarketplaceSubscription::where('workspace_id', $workspace->id)->whereIn('status', ['active', 'trialing'])->update(['status' => 'canceled', 'ends_at' => now()]);

            $periodDays = $billingInterval === 'yearly' ? 365 : 30;
            $subscription = MarketplaceSubscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'user_id' => $transaction->user_id,
                'status' => 'active',
                'billing_interval' => $billingInterval,
                'current_period_start' => now(),
                'current_period_ends_at' => now()->addDays($periodDays),
                'trial_ends_at' => null,
                'chapa_tx_ref' => $transaction->tx_ref,
                'provider' => 'chapa',
            ]);

            $invoiceNumber = 'NF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            MarketplacePaymentReceipt::create([
                'transaction_id' => $transaction->id,
                'subscription_id' => $subscription->id,
                'workspace_id' => $workspace->id,
                'amount_etb' => $transaction->amount_etb,
                'currency' => 'ETB',
                'invoice_number' => $invoiceNumber,
                'issued_at' => now(),
            ]);
        });
    }
}
