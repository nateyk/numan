<?php

namespace App\Services;

use App\Models\MarketplaceSubscription;
use Illuminate\Support\Facades\DB;

/**
 * The reveal numbers for the admin marketplace dashboard.
 *
 * Counts only, deliberately. Nothing here resolves a counterparty or returns a
 * contact detail; an analytics screen that could reveal a contact would be the
 * very leak this work set out to close.
 */
class MarketplaceAdminAnalyticsService
{
    public function __construct(
        protected ContactRevealAnalyticsService $reveals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function revealSummary(): array
    {
        $daily = $this->reveals->daily(30);
        $conversion = $this->reveals->conversion(7);

        return [
            'daily' => $daily['days'],
            'totals' => $daily['totals'],
            'conversion' => [
                'blocked_users' => $conversion['blocked_users'],
                'converted_users' => $conversion['converted_users'],
                'conversion_rate' => $conversion['conversion_rate'],
                'window_days' => $conversion['window_days'],
            ],
            'ledger_rows' => DB::table('contact_reveals')->count(),
            'distinct_pairs' => DB::table('contact_reveals')
                ->selectRaw('revealer_id, revealed_id')
                ->distinct()
                ->count(),
            'blocked_rows' => DB::table('contact_reveal_blocks')->count(),
            'paid_subscribers' => MarketplaceSubscription::query()
                ->whereIn('status', ['active', 'trialing'])
                ->count(),
        ];
    }
}
