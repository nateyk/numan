<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Identifies paid but canceled transactions for human settlement review.
 *
 * This command NEVER fulfills a plan, changes a payment, or issues a refund.
 */
class AuditCanceledChapaPayments extends Command
{
    protected $signature = 'chapa:audit-canceled
                            {--ref= : Audit a single NF transaction reference}
                            {--limit=100 : Maximum canceled transactions to check in this invocation}';

    protected $description = 'Read-only review of canceled Chapa payments that may need refund or settlement';

    public function handle(ChapaService $chapa): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $reference = $this->option('ref');

        $query = MarketplacePaymentTransaction::query()
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
            ->where('status', 'canceled')
            ->when(filled($reference), fn ($q) => $q->where('tx_ref', $reference))
            ->orderBy('id')
            ->limit($limit);

        $checked = 0;
        $paid = 0;
        $unresolved = 0;

        foreach ($query->get() as $transaction) {
            $checked++;

            try {
                $verified = $chapa->verifyTransaction($transaction->tx_ref);
            } catch (Throwable $e) {
                // No payer contact data is logged or printed.
                report($e);
                $this->error("{$transaction->tx_ref}: verification unavailable; manual follow-up required.");
                $unresolved++;

                continue;
            }

            $data = $verified['data'] ?? null;
            $providerPaid = ($verified['status'] ?? null) === 'success'
                && is_array($data)
                && in_array($data['status'] ?? null, ['success', 'paid'], true);

            if ($providerPaid) {
                if ($chapa->verifiedPaymentMatches($verified, $transaction)) {
                    $this->warn("{$transaction->tx_ref}: PAID AFTER CANCELLATION — refund/settlement review required ({$transaction->amount_etb} ETB).");
                    $paid++;
                } else {
                    $this->error("{$transaction->tx_ref}: provider reports paid with mismatched details; investigate manually.");
                    $unresolved++;
                }
            } else {
                $this->line("{$transaction->tx_ref}: no verified payment recorded.");
            }
        }

        $this->info("Audited {$checked}; paid and needs review: {$paid}; unresolved: {$unresolved}. No records or funds were changed.");

        return $unresolved > 0 ? self::FAILURE : self::SUCCESS;
    }
}
