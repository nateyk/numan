<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Notifications\ManualPaymentExpired;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lapses manual payment requests nobody reviewed.
 *
 * Expiry grants nothing. It closes the request, frees the workspace's one open
 * slot, and tells the payer. There is deliberately no path from an expired row
 * to a subscription: an admin can reopen it, which puts it back in the queue for
 * a human decision, and a human decision goes through fulfill().
 *
 * Every transition takes a row lock and requires the row to be open, so an
 * expiry that races a confirm resolves to exactly one of the two outcomes
 * rather than both.
 */
class ManualPaymentExpiryService
{
    public function __construct(
        protected MarketplaceSettingsService $settings,
    ) {}

    /**
     * Days an admin has to review.
     */
    public function windowDays(): int
    {
        $days = (int) $this->settings->get('free_manual_payment_expiry_days');

        return $days > 0 ? $days : 14;
    }

    /**
     * When a request submitted now would lapse.
     */
    public function expiryFromNow(): Carbon
    {
        return now()->addDays($this->windowDays());
    }

    /**
     * Lapse every open request past its window.
     *
     * @param  bool  $dryRun  Report without writing.
     * @return array{examined: int, expired: int, notified: int, rows: list<array<string, mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $candidates = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_MANUAL)
            ->where('status', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS)
            ->whereNotNull('manual_expires_at')
            ->where('manual_expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        $rows = [];
        $expired = 0;

        foreach ($candidates as $transaction) {
            $rows[] = [
                'id' => $transaction->id,
                'tx_ref' => $transaction->tx_ref,
                'workspace_id' => $transaction->workspace_id,
                'plan' => $transaction->plan?->name ?? 'unknown',
                'amount_etb' => (string) $transaction->amount_etb,
                'method' => $transaction->manual_method,
                'submitted_at' => optional($transaction->manual_submitted_at)->toDateTimeString(),
                'expired_at' => optional($transaction->manual_expires_at)->toDateTimeString(),
            ];

            if (! $dryRun) {
                $this->expire($transaction);
                $expired++;
            }
        }

        return [
            'examined' => $candidates->count(),
            'expired' => $expired,
            'notified' => $dryRun ? 0 : $expired,
            'rows' => $rows,
        ];
    }

    /**
     * Lapse one row, if it is still open.
     *
     * Returns false when the row was already taken by a confirm, a reject or a
     * concurrent expiry. The caller can then treat it as a no-op rather than an
     * error.
     */
    public function expire(MarketplacePaymentTransaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction): bool {
            $locked = MarketplacePaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return false;
            }

            // Only an open row may lapse. A confirmed row must never be walked
            // back by the expiry sweep.
            if (! $locked->isOpenManual()) {
                return false;
            }

            $locked->update([
                'status' => MarketplacePaymentTransaction::MANUAL_EXPIRED,
                // Frees the workspace's single open slot.
                'open_manual_workspace_id' => null,
            ]);

            // Expiry grants nothing. Checked rather than assumed: a subscription
            // carrying this transaction's reference would mean expiry somehow
            // reached fulfil(), which is the one thing it must never do.
            $this->assertGrantedNothing($locked);

            $this->notifyPayer($locked);

            return true;
        });
    }

    /**
     * Tell the payer, if we can find them.
     */
    private function notifyPayer(MarketplacePaymentTransaction $transaction): void
    {
        $user = $transaction->user;

        if ($user === null) {
            return;
        }

        $user->notify(new ManualPaymentExpired($transaction));
    }

    /**
     * Prove this transaction produced no subscription.
     *
     * marketplace_subscriptions.chapa_tx_ref is the unique reference a granted
     * subscription carries, and it is set from the transaction's tx_ref whatever
     * the provider. So a row matching here means this transaction was fulfilled,
     * which expiry must never cause.
     */
    private function assertGrantedNothing(MarketplacePaymentTransaction $transaction): void
    {
        $granted = MarketplaceSubscription::query()
            ->where('chapa_tx_ref', $transaction->tx_ref)
            ->exists();

        if ($granted) {
            throw new LogicException(
                "Manual payment {$transaction->tx_ref} was expired but a subscription already "
                .'references it. Expiry must never grant access; this is a bug.'
            );
        }
    }
}
