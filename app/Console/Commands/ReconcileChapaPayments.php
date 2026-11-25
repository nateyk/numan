<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaSubscriptionVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileChapaPayments extends Command
{
    protected $signature = 'chapa:reconcile
                            {--all : Include older pending attempts for manual financial reconciliation}
                            {--dry-run : Preview unresolved local Chapa references without calling the provider or changing records}';

    protected $description = 'Verify outstanding Chapa payments, settle verified receipts and report unresolved attempts';

    public function handle(ChapaSubscriptionVerifier $verifier): int
    {
        // Never reconcile manual transfers against Chapa. The default two-day
        // window keeps scheduled runs bounded; --all is an explicit historical
        // financial review and may fulfill old but genuinely paid attempts.
        $pendingTxs = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
            ->whereIn('status', ['pending', 'initialized'])
            ->when(! $this->option('all'), fn ($query) => $query->where('created_at', '>', now()->subDays(2)))
            ->orderBy('id')
            ->get();

        if ($pendingTxs->isEmpty()) {
            $this->info('No pending Chapa transactions to reconcile.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: No Chapa API calls, receipts, subscriptions, or transaction records will change.');
            $this->info("Would inspect {$pendingTxs->count()} unresolved Chapa transaction(s).");

            foreach ($pendingTxs as $tx) {
                $this->line(sprintf(
                    '  [%s] %s %s, created %s',
                    $tx->tx_ref,
                    $tx->status,
                    $tx->mode,
                    $tx->created_at?->toIso8601String() ?? 'unknown',
                ));
            }

            return self::SUCCESS;
        }

        $this->info("Reconciling {$pendingTxs->count()} pending Chapa transaction(s)...");

        $success = 0;
        $failed = 0;

        foreach ($pendingTxs as $tx) {
            try {
                // Reuse the same verified settlement path as the authenticated
                // payer check and both callback endpoints. It verifies amount,
                // currency, mode and reference, and never claims activation
                // until a committed verified payment AND receipt exist.
                $status = $verifier->verify($tx);

                if ($status === 'verified') {
                    $this->info("  [{$tx->tx_ref}] Fulfilled successfully.");
                    $success++;

                    continue;
                }

                $failed++;

                match ($status) {
                    'failed' => $this->warn("  [{$tx->tx_ref}] Chapa confirmed payment failed; no subscription was granted."),
                    'mismatch' => $this->error("  [{$tx->tx_ref}] Verified payment details missing or mismatched. Requires review."),
                    'canceled' => $this->warn("  [{$tx->tx_ref}] Canceled; no entitlement restored. Review any late charge."),
                    default => $this->warn("  [{$tx->tx_ref}] Not yet verified by Chapa."),
                };
            } catch (Throwable $e) {
                // Provider messages may contain customer metadata. Record the
                // exception class only; the full throwable goes to the error
                // reporter, never into a routine scheduler's console output.
                Log::warning('Chapa reconciliation verification failed', [
                    'tx_ref' => $tx->tx_ref,
                    'exception_class' => get_class($e),
                ]);
                report($e);
                $this->error("  [{$tx->tx_ref}] Verification unavailable; will retry.");
                $failed++;
            }
        }

        $this->info("Reconcile complete: {$success} fulfilled, {$failed} failed.");

        return self::SUCCESS;
    }
}
