<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileChapaPayments extends Command
{
    protected $signature = 'chapa:reconcile';

    protected $description = 'Verify pending Chapa payments and fulfill subscriptions';

    public function __construct(
        protected ChapaService $chapa,
        protected MarketplaceFulfillmentService $fulfillment,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pendingTxs = MarketplacePaymentTransaction::whereIn('status', ['pending', 'initialized'])
            ->where('created_at', '>', now()->subDays(2))
            ->get();

        if ($pendingTxs->isEmpty()) {
            $this->info('No pending Chapa transactions to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Reconciling {$pendingTxs->count()} pending Chapa transaction(s)...");

        $success = 0;
        $failed = 0;

        foreach ($pendingTxs as $tx) {
            try {
                $verified = $this->chapa->verifyTransaction($tx->tx_ref);
                $data = $verified['data'] ?? $verified;

                $isSuccess = ($verified['status'] ?? null) === 'success'
                    && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

                if (! $isSuccess) {
                    $this->warn("  [{$tx->tx_ref}] Not yet verified by Chapa.");
                    $failed++;

                    continue;
                }

                $expected = (string) $tx->amount_etb;
                $got = (string) ($data['amount'] ?? '');
                $currency = $data['currency'] ?? 'ETB';

                if ($currency !== 'ETB' || ($got !== '' && $got !== $expected && (float) $got !== (float) $expected)) {
                    $this->error("  [{$tx->tx_ref}] Amount mismatch: expected {$expected}, got {$got}.");
                    $failed++;

                    continue;
                }

                $this->fulfillment->fulfill($tx, $verified);
                $this->info("  [{$tx->tx_ref}] Fulfilled successfully.");
                $success++;
            } catch (\Throwable $e) {
                Log::error('Chapa reconcile failed', ['tx_ref' => $tx->tx_ref, 'error' => $e->getMessage()]);
                $this->error("  [{$tx->tx_ref}] Error: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Reconcile complete: {$success} fulfilled, {$failed} failed.");

        return self::SUCCESS;
    }
}
