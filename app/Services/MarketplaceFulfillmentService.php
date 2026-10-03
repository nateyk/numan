<?php

namespace App\Services;

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceFulfillmentService
{
    public function fulfill(MarketplacePaymentTransaction $transaction, array $verified): void
    {
        DB::transaction(function () use ($transaction, $verified) {
            $transaction = MarketplacePaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $transaction) {
                return;
            }

            if ($transaction->status === 'verified' && $transaction->verified_at) {
                return;
            }

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

            $billingInterval = $transaction->billing_interval ?? 'monthly';
            if (! in_array($billingInterval, ['weekly', 'monthly', 'yearly'])) {
                $billingInterval = 'monthly';
            }

            // Cancel previous active subs
            MarketplaceSubscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'canceled', 'ends_at' => now()]);

            $periodDays = match ($billingInterval) {
                'weekly' => 7,
                'yearly' => 365,
                default => 30,
            };

            $periodEnd = now()->addDays($periodDays);

            $subscription = MarketplaceSubscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'user_id' => $transaction->user_id,
                'status' => 'active',
                'billing_interval' => $billingInterval,
                'current_period_start' => now(),
                'current_period_ends_at' => $periodEnd,
                'ends_at' => $periodEnd,
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
