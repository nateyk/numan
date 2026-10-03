<?php

use App\Services\MarketplaceBackfillService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(MarketplaceBackfillService::class);

        $subscriptionsUpdated = $service->backfillSubscriptionEndsAt();
        $service->backfillTransactionIntervals();

        if ($subscriptionsUpdated > 0) {
            $this->info("Backfilled ends_at on {$subscriptionsUpdated} subscription(s).");
        }
    }

    public function down(): void
    {
        // Intentionally empty. Clearing backfilled values would re-break
        // subscription expiry for rows this migration corrected.
    }
};
