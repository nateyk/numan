<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ETB revenue, split by provider and interval.
 *
 * The application had no ETB totals at all before this: RevenueAnalyticsController
 * reads Cashier's Subscription table and stripe_status, so every birr NumanFreight
 * took through Chapa or a bank transfer was invisible to it.
 *
 * Only verified rows count. A pending Chapa transaction is not revenue, and a
 * pending manual request is not revenue and does not grant anything, so counting
 * either would overstate what came in.
 */
class MarketplaceRevenueAnalyticsService
{
    public const PROVIDERS = [
        MarketplacePaymentTransaction::PROVIDER_CHAPA,
        MarketplacePaymentTransaction::PROVIDER_MANUAL,
    ];

    public const INTERVALS = ['weekly', 'monthly', 'yearly'];

    /**
     * The headline totals.
     *
     * @return array{total_etb: float, by_provider: array<string, float>, by_interval: array<string, float>, verified_count: int, pending_count: int}
     */
    public function totals(): array
    {
        $verified = $this->verifiedQuery();

        $byProvider = [];

        foreach (self::PROVIDERS as $provider) {
            $byProvider[$provider] = (float) (clone $verified)
                ->where('provider', $provider)
                ->sum('amount_etb');
        }

        $byInterval = [];

        foreach (self::INTERVALS as $interval) {
            $byInterval[$interval] = (float) (clone $verified)
                ->where('billing_interval', $interval)
                ->sum('amount_etb');
        }

        return [
            'total_etb' => (float) (clone $verified)->sum('amount_etb'),
            'by_provider' => $byProvider,
            'by_interval' => $byInterval,
            'verified_count' => (int) (clone $verified)->count(),
            'pending_count' => MarketplacePaymentTransaction::query()
                ->whereIn('status', ['pending', 'initialized', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS])
                ->count(),
        ];
    }

    /**
     * Verified revenue per day.
     *
     * Zero-filled for the same reason the reveal analytics are: a chart that
     * skips days lies about the trend.
     *
     * @return list<array{date: string, total: float, chapa: float, manual: float}>
     */
    public function daily(int $days = 30): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = $this->verifiedQuery()
            ->where('verified_at', '>=', $from->copy()->startOfDay())
            ->get(['verified_at', 'amount_etb', 'provider']);

        $buckets = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->verified_at)->toDateString();

            $buckets[$key] ??= ['total' => 0.0, 'chapa' => 0.0, 'manual' => 0.0];

            $amount = (float) $row->amount_etb;
            $provider = $row->provider === MarketplacePaymentTransaction::PROVIDER_MANUAL
                ? 'manual'
                : 'chapa';

            $buckets[$key]['total'] += $amount;
            $buckets[$key][$provider] += $amount;
        }

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $bucket = $buckets[$date] ?? ['total' => 0.0, 'chapa' => 0.0, 'manual' => 0.0];

            $series[] = [
                'date' => $date,
                'total' => round($bucket['total'], 2),
                'chapa' => round($bucket['chapa'], 2),
                'manual' => round($bucket['manual'], 2),
            ];
        }

        return $series;
    }

    /**
     * Verified revenue per month, for a longer view.
     *
     * @return list<array{month: string, total: float, chapa: float, manual: float, count: int}>
     */
    public function monthly(int $months = 12): array
    {
        $from = Carbon::today()->startOfMonth()->subMonths($months - 1);

        $rows = $this->verifiedQuery()
            ->where('verified_at', '>=', $from)
            ->get(['verified_at', 'amount_etb', 'provider']);

        $buckets = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->verified_at)->format('Y-m');

            $buckets[$key] ??= [
                'total' => 0.0,
                'chapa' => 0.0,
                'manual' => 0.0,
                'count' => 0,
            ];

            $amount = (float) $row->amount_etb;
            $provider = $row->provider === MarketplacePaymentTransaction::PROVIDER_MANUAL
                ? 'manual'
                : 'chapa';

            $buckets[$key]['total'] += $amount;
            $buckets[$key][$provider] += $amount;
            $buckets[$key]['count']++;
        }

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $from->copy()->addMonths($i)->format('Y-m');
            $bucket = $buckets[$month] ?? ['total' => 0.0, 'chapa' => 0.0, 'manual' => 0.0, 'count' => 0];

            $series[] = [
                'month' => $month,
                'total' => round($bucket['total'], 2),
                'chapa' => round($bucket['chapa'], 2),
                'manual' => round($bucket['manual'], 2),
                'count' => $bucket['count'],
            ];
        }

        return $series;
    }

    /**
     * Revenue by plan, so a price change is visible against what was sold.
     *
     * @return list<array{plan: string, total: float, count: int}>
     */
    public function byPlan(): array
    {
        $rows = $this->verifiedQuery()
            ->join('marketplace_plans', 'marketplace_plans.id', '=', 'marketplace_payment_transactions.plan_id')
            ->groupBy('marketplace_plans.slug')
            ->selectRaw('marketplace_plans.slug, SUM(amount_etb) as total, COUNT(*) as count')
            ->get();

        return $rows->map(fn ($row): array => [
            'plan' => (string) $row->slug,
            'total' => round((float) $row->total, 2),
            'count' => (int) $row->count,
        ])->values()->all();
    }

    /**
     * Only verified rows are revenue.
     */
    private function verifiedQuery(): Builder
    {
        return DB::table('marketplace_payment_transactions')
            ->where('status', MarketplacePaymentTransaction::MANUAL_CONFIRMED)
            ->whereNotNull('verified_at');
    }
}
