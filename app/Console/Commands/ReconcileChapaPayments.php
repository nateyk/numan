<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileChapaPayments extends Command
{
    protected $signature = 'chapa:reconcile
                            {--all : Include older pending attempts for manual financial reconciliation}';

    protected $description = 'Verify pending Chapa payments and fulfill subscriptions';

    public function __construct(
        protected ChapaService $chapa,
        protected MarketplaceFulfillmentService $fulfillment,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // provider = 'chapa' is load-bearing, not a nicety. Without it a manual
        // payment sitting in pending_confirmation with an NF reference would be
        // sent to Chapa's verify API, which either fails noisily or, worse,
        // matches something it should not.
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

                if (! $this->chapa->verifiedPaymentMatches($verified, $tx)) {
                    $this->error("  [{$tx->tx_ref}] Verified payment details missing or mismatched.");
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
