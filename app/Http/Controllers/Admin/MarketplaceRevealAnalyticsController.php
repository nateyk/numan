<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceAdminAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reveal paywall numbers.
 *
 * Counts only. Nothing here resolves a counterparty or returns a contact
 * detail — an analytics screen that could reveal a contact would be the very
 * leak the reveal gate exists to prevent.
 */
class MarketplaceRevealAnalyticsController extends Controller
{
    public function __construct(
        protected MarketplaceAdminAnalyticsService $analytics,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/marketplace/reveal-analytics', [
            'summary' => $this->analytics->revealSummary(),
        ]);
    }

    /**
     * ETB revenue, which the application had no totals for before this.
     */
    public function revenue(): Response
    {
        return Inertia::render('admin/marketplace/revenue', [
            'summary' => $this->analytics->revenueSummary(),
        ]);
    }
}
