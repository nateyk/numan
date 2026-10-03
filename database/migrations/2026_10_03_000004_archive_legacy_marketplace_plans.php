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

        $this->stampInterestWindow(30);
    }

    /**
     * The retired plans counted interests over 30 days. That is now an
     * explicit limit rather than something inferred from the presence of an
     * interests_per_month key, so existing subscribers keep their 30 day
     * window without relying on inference.
     */
    private function stampInterestWindow(int $days): void
    {
        $plans = DB::table('marketplace_plans')
            ->whereIn('slug', self::ARCHIVED_SLUGS)
            ->get();

        foreach ($plans as $plan) {
            $limits = json_decode($plan->limits ?? '[]', true) ?: [];

            if (($limits['interest_window_days'] ?? null) === $days) {
                continue;
            }

            $limits['interest_window_days'] = $days;

            DB::table('marketplace_plans')
                ->where('id', $plan->id)
                ->update([
                    'limits' => json_encode($limits),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Left archived. Re-activating them would hand live plans back to
        // users who were moved onto the standard plan.
    }
};
