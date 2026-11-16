<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;

/**
 * Reconcile one Chapa subscription attempt from a verified provider response.
 *
 * Used by the browser return, Chapa's server-to-server callback and the
 * authenticated "check status" action. A redirect or callback query string
 * is never evidence of payment.
 */
class ChapaSubscriptionVerifier
{
    public function __construct(
        protected ChapaService $chapa,
        protected MarketplaceFulfillmentService $fulfillment,
    ) {}

    /**
     * @return 'verified'|'pending'|'mismatch'|'canceled'
     */
    public function verify(MarketplacePaymentTransaction $transaction): string
    {
        // Canceled purchases (including after account deletion) must never
        // regain paid access from a delayed Chapa callback.
        if (! $transaction->isChapa()
            || $transaction->status === 'canceled'
            || $transaction->user === null) {
            return 'canceled';
        }

        if ($transaction->status === 'verified' && $transaction->verified_at !== null) {
            // A historical partial settlement lacking its receipt needs a
            // human ledger review; do not announce a successful subscription.
            return $transaction->receipt()->exists() ? 'verified' : 'mismatch';
        }

        $verified = $this->chapa->verifyTransaction($transaction->tx_ref);
        $data = $verified['data'] ?? null;

        if (! is_array($data)
            || ($verified['status'] ?? null) !== 'success'
            || ! in_array($data['status'] ?? null, ['success', 'paid'], true)) {
            return 'pending';
        }

        if (! $this->chapa->verifiedPaymentMatches($verified, $transaction)) {
            return 'mismatch';
        }

        $this->fulfillment->fulfill($transaction, $verified);

        // Fulfillment may have refused a concurrent cancellation. Never
        // tell the payer a subscription was activated without a committed
        // verified transaction and its receipt.
        $transaction->refresh();

        if ($transaction->status === 'canceled') {
            return 'canceled';
        }

        return $transaction->status === 'verified' && $transaction->verified_at !== null
            && $transaction->receipt()->exists()
                ? 'verified'
                : 'pending';
    }
}
