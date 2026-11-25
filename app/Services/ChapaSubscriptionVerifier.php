<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Support\Facades\DB;

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
     * @return 'verified'|'pending'|'failed'|'mismatch'|'canceled'
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

        // Only a definitive transaction-level failure from the authenticated
        // verification endpoint can release the checkout retry guard. An API
        // error (top-level failed) and a pending payment remain unresolved.
        if (($verified['status'] ?? null) === 'success'
            && is_array($data)
            && in_array($data['status'] ?? null, ['failed', 'cancelled', 'canceled'], true)) {
            if ((isset($data['tx_ref']) && (string) $data['tx_ref'] !== $transaction->tx_ref)
                || (isset($data['mode']) && $data['mode'] !== $transaction->mode)) {
                return 'mismatch';
            }

            return DB::transaction(function () use ($transaction, $verified): string {
                // A successful callback may have settled this payment while our
                // failed-status verification was in flight. Never overwrite
                // its verified ledger status or the already-issued receipt.
                $current = MarketplacePaymentTransaction::whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($current->status === 'canceled') {
                    return 'canceled';
                }

                if ($current->status === 'verified' && $current->verified_at !== null) {
                    return $current->receipt()->exists() ? 'verified' : 'mismatch';
                }

                $current->update([
                    'status' => 'failed',
                    'provider_response' => $verified,
                ]);

                return 'failed';
            });
        }

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
