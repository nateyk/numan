<?php

namespace App\Services;

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceFulfillmentService
{
    public function fulfill(MarketplacePaymentTransaction $transaction, array $verified): void
    {
        DB::transaction(function () use ($transaction, $verified) {
            // Different successful payments can arrive simultaneously. Lock the
            // common subscription owner before examining either transaction so
            // both renewals extend from the latest committed period.
            $workspace = Workspace::whereKey($transaction->workspace_id)->lockForUpdate()->first();
            $transaction = MarketplacePaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $transaction) {
                return;
            }

            if ($transaction->status === 'verified' && $transaction->verified_at) {
                return;
            }

            // A deleted profile or canceled attempt cannot be reactivated by
            // a late provider webhook after account deletion.
            if ($transaction->status === 'canceled' || $transaction->user === null) {
                return;
            }

            $plan = $transaction->plan;
            if (! $workspace || ! $plan) {
                throw new \RuntimeException('Cannot fulfill a payment without its subscription owner and plan.');
            }

            $data = $verified['data'] ?? $verified;
            $chapaRef = $data['reference'] ?? $data['chapa_ref'] ?? $data['tx_ref'] ?? null;

            $transaction->update([
                'status' => 'verified',
                'verified_at' => now(),
                'chapa_ref' => $chapaRef ?? $transaction->chapa_ref,
                'provider_response' => $verified,
            ]);

            $billingInterval = $transaction->billing_interval ?? 'monthly';
            if (! in_array($billingInterval, ['weekly', 'monthly', 'yearly'], true)) {
                $billingInterval = 'monthly';
            }

            // A renewal extends the current period instead of overwriting it, so
            // an early upgrade never loses the days already paid for.
            $current = MarketplaceSubscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest('ends_at')
                ->first();

            $periodStart = $current?->ends_at?->isFuture() ? $current->ends_at->copy() : now();

            // The previous subscription keeps its original ends_at so access is
            // continuous; only the status changes.
            if ($current) {
                $current->update(['status' => 'superseded']);
            }

            $periodDays = match ($billingInterval) {
                'weekly' => 7,
                'yearly' => 365,
                default => 30,
            };

            $periodEnd = $periodStart->copy()->addDays($periodDays);

            $subscription = MarketplaceSubscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'user_id' => $transaction->user_id,
                'status' => 'active',
                'billing_interval' => $billingInterval,
                'current_period_start' => $periodStart,
                'current_period_ends_at' => $periodEnd,
                'ends_at' => $periodEnd,
                'trial_ends_at' => null,
                'chapa_tx_ref' => $transaction->tx_ref,
                // From the transaction, not hardcoded: a manual grant must not be
                // labelled as Chapa, or the revenue split by provider is wrong.
                // The column keeps its historical name because the unique index
                // on it is what stops a double grant.
                'provider' => $transaction->provider ?: MarketplacePaymentTransaction::PROVIDER_CHAPA,
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

            // Inside the same transaction, so a failure here rolls the whole
            // grant back and listings stay hidden. This is the only thing that
            // ever clears hidden_at.
            app(MarketplaceListingVisibilityService::class)
                ->restoreHiddenPosts($workspace);
        });
    }
}
