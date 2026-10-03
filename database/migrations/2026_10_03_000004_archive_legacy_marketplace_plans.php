<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The legacy plans retired when the marketplace moved to a single paid
     * plan with three durations.
     *
     * These are archived with is_active = false only. They are deliberately
     * NOT soft deleted: existing subscriptions resolve their limits through
     * MarketplaceSubscription::plan(), so a soft-deleted plan would resolve
     * to null and cut off access for subscribers who already paid.
     *
     * The seeder never re-activates these.
     */
    private const ARCHIVED_SLUGS = ['weekly', 'free', 'pro', 'business'];

    public function up(): void
    {
        DB::table('marketplace_plans')
            ->whereIn('slug', self::ARCHIVED_SLUGS)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Left archived. Re-activating them would hand live plans back to
        // users who were moved onto the standard plan.
    }
};
