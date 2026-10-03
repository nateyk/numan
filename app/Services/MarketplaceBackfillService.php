<?php

namespace App\Services;

use App\Models\MarketplacePlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarketplaceBackfillService
{
    /**
     * Backfill ends_at on active/trialing subscriptions that predate the fix.
     */
    public function backfillSubscriptionEndsAt(): int
    {
        if (! Schema::hasTable('marketplace_subscriptions')) {
            return 0;
        }

        return DB::table('marketplace_subscriptions')
            ->whereNull('ends_at')
            ->whereIn('status', ['active', 'trialing'])
            ->update(['ends_at' => DB::raw('current_period_ends_at')]);
    }

    /**
     * Backfill billing_interval on transactions, then report any subscription
     * that was granted the wrong duration.
     */
    public function backfillTransactionIntervals(): void
    {
        if (! Schema::hasTable('marketplace_payment_transactions')) {
            return;
        }

        $transactions = DB::table('marketplace_payment_transactions')
            ->whereNull('billing_interval')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $mismatches = [];

        foreach ($transactions as $tx) {
            $interval = $this->parseIntervalFromTxRef($tx->tx_ref)
                ?? $this->matchIntervalByAmount((float) $tx->amount_etb, $tx->plan_id);

            if ($interval === null) {
                Log::warning('Marketplace backfill: could not determine billing_interval', [
                    'transaction_id' => $tx->id,
                    'tx_ref' => $tx->tx_ref,
                    'amount_etb' => $tx->amount_etb,
                ]);

                continue;
            }

            DB::table('marketplace_payment_transactions')
                ->where('id', $tx->id)
                ->update(['billing_interval' => $interval]);

            $mismatch = $this->detectWrongDuration($tx, $interval);
            if ($mismatch !== null) {
                $mismatches[] = $mismatch;
            }
        }

        $this->reportWrongDurations($mismatches);
    }

    /**
     * Compare the days actually granted against the days implied by the interval.
     */
    private function detectWrongDuration(object $tx, string $interval): ?array
    {
        $sub = DB::table('marketplace_subscriptions')
            ->where('chapa_tx_ref', $tx->tx_ref)
            ->first();

        if (! $sub || ! $sub->current_period_start || ! $sub->current_period_ends_at) {
            return null;
        }

        $expectedDays = $this->daysForInterval($interval);
        $actualDays = (int) Carbon::parse($sub->current_period_start)
            ->diffInDays(Carbon::parse($sub->current_period_ends_at), false);

        if (abs($actualDays - $expectedDays) <= 1) {
            return null;
        }

        return [
            'subscription_id' => $sub->id,
            'workspace_id' => $sub->workspace_id,
            'plan' => MarketplacePlan::find($tx->plan_id)?->name ?? 'unknown',
            'tx_ref' => $tx->tx_ref,
            'amount_paid_etb' => (float) $tx->amount_etb,
            'interval_implied_by_amount' => $interval,
            'expected_days' => $expectedDays,
            'days_actually_granted' => $actualDays,
        ];
    }

    /**
     * Write the mismatch report to a CSV and log a summary. Never mutates data.
     *
     * @param  list<array<string, mixed>>  $mismatches
     */
    private function reportWrongDurations(array $mismatches): void
    {
        if ($mismatches === []) {
            return;
        }

        Log::warning('Marketplace backfill: subscriptions granted the wrong duration', [
            'count' => count($mismatches),
        ]);

        $filename = 'wrong-duration-subscriptions-'.now()->format('Ymd').'.csv';
        $path = 'reports/'.$filename;

        $handle = fopen('php://temp', 'r+');

        try {
            fputcsv($handle, [
                'subscription_id',
                'workspace_id',
                'plan',
                'tx_ref',
                'amount_paid_etb',
                'interval_implied_by_amount',
                'expected_days',
                'days_actually_granted',
            ]);

            foreach ($mismatches as $row) {
                fputcsv($handle, [
                    $row['subscription_id'],
                    $row['workspace_id'],
                    $row['plan'],
                    $row['tx_ref'],
                    $row['amount_paid_etb'],
                    $row['interval_implied_by_amount'],
                    $row['expected_days'],
                    $row['days_actually_granted'],
                ]);
            }

            rewind($handle);
            Storage::disk('local')->put($path, stream_get_contents($handle));

            Log::warning("Marketplace backfill: wrong-duration report written to {$path}");
        } catch (Throwable $e) {
            Log::error('Marketplace backfill: failed to write wrong-duration report', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return int|null
     */
    public function daysForInterval(string $interval): int
    {
        return match ($interval) {
            'weekly' => 7,
            'yearly' => 365,
            default => 30,
        };
    }

    /**
     * CheckoutController builds tx_ref as "NF-{slug}-{interval}_{uniqid}".
     */
    public function parseIntervalFromTxRef(?string $txRef): ?string
    {
        if (! $txRef) {
            return null;
        }

        if (preg_match('/^NF-[a-z0-9-]*-(weekly|monthly|yearly)_/', $txRef, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fallback: infer the interval by matching the paid amount against plan prices.
     */
    public function matchIntervalByAmount(float $amount, ?int $planId): ?string
    {
        if ($planId === null || $amount <= 0) {
            return null;
        }

        $plan = MarketplacePlan::find($planId);
        if (! $plan) {
            return null;
        }

        $amount = round($amount, 2);

        $candidates = [
            'weekly' => (float) $plan->price_weekly_etb,
            'monthly' => (float) $plan->price_monthly_etb,
            'yearly' => (float) $plan->price_yearly_etb,
        ];

        foreach ($candidates as $interval => $price) {
            if ($price > 0 && round($price, 2) === $amount) {
                return $interval;
            }
        }

        return null;
    }
}
