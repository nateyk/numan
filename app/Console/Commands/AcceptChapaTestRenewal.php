<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Services\ChapaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read-only acceptance of two real Chapa Test Mode hosted checkouts.
 *
 * The operator first completes both checkouts through the real browser flow.
 * This command verifies their actual references against Chapa, then confirms
 * that Numan stored one receipt per payment and retained paid renewal time.
 * This is NOT a simulator and does not initiate a new charge.
 */
class AcceptChapaTestRenewal extends Command
{
    protected $signature = 'chapa:accept-renewal
                            {initial : Merchant reference of the first completed Test Mode checkout}
                            {renewal : Merchant reference of the completed Test Mode renewal}';

    protected $description = 'Read-only provider-backed Test Mode checkout-to-renewal acceptance';

    public function handle(ChapaService $chapa): int
    {
        $key = (string) config('chapa.secretKey');
        $baseUrl = rtrim((string) config('chapa.baseUrl'), '/');

        if (config('chapa.mode') !== 'test'
            || ! preg_match('/^(CHASECK_TEST|CHAPA_TEST)[_-]/', $key)) {
            $this->error('Test Mode acceptance requires CHAPA_MODE=test and a Chapa TEST private key.');

            return self::FAILURE;
        }

        if (! str_ends_with($baseUrl, '/v1')) {
            $this->error('This Numan integration uses legacy Chapa v1. Review the configured API base URL; do not run v2 responses through the v1 verifier.');

            return self::FAILURE;
        }

        $initial = MarketplacePaymentTransaction::query()
            ->where('tx_ref', $this->argument('initial'))->first();
        $renewal = MarketplacePaymentTransaction::query()
            ->where('tx_ref', $this->argument('renewal'))->first();

        if (! $initial || ! $renewal || $initial->id === $renewal->id) {
            $this->error('Provide two different existing Numan payment references.');

            return self::FAILURE;
        }

        if ($initial->workspace_id !== $renewal->workspace_id
            || $initial->user_id !== $renewal->user_id
            || $initial->provider !== 'chapa'
            || $renewal->provider !== 'chapa'
            || $initial->mode !== 'test'
            || $renewal->mode !== 'test') {
            $this->error('Both records must be Test Mode Chapa payments for the same single-profile payer.');

            return self::FAILURE;
        }

        try {
            foreach ([$initial, $renewal] as $transaction) {
                // Never trust a local verified flag alone for genuine provider
                // acceptance. Check Chapa in Test Mode on every invocation.
                $provider = $chapa->verifyTransaction($transaction->tx_ref);

                if (! $chapa->verifiedPaymentMatches($provider, $transaction)) {
                    $this->error("Provider verification did not match {$transaction->tx_ref}.");

                    return self::FAILURE;
                }

                if ($transaction->status !== 'verified'
                    || ! $transaction->verified_at
                    || ! $transaction->receipt()->exists()) {
                    $this->error("Numan has not fully fulfilled {$transaction->tx_ref} with a receipt.");

                    return self::FAILURE;
                }
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('Provider verification is unavailable; acceptance is not complete.');

            return self::FAILURE;
        }

        $first = MarketplaceSubscription::where('chapa_tx_ref', $initial->tx_ref)->first();
        $second = MarketplaceSubscription::where('chapa_tx_ref', $renewal->tx_ref)->first();

        if (! $first || ! $second
            || $first->workspace_id !== $initial->workspace_id
            || $second->workspace_id !== $initial->workspace_id
            || ! $first->ends_at || ! $second->current_period_start
            || ! $first->ends_at->equalTo($second->current_period_start)
            || ! $second->ends_at || ! $second->ends_at->greaterThan($first->ends_at)) {
            $this->error('Subscription renewal did not preserve a contiguous paid period.');

            return self::FAILURE;
        }

        $this->info('PASS: Both distinct checkouts were verified directly with Chapa Test Mode.');
        $this->info('PASS: Numan has one verified transaction, subscription, and receipt for each reference.');
        $this->info('PASS: The renewal begins at the prior paid period end (no paid days lost).');
        $this->line('No payment, refund, entitlement, or database record was changed.');

        return self::SUCCESS;
    }
}
