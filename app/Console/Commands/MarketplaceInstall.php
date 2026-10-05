<?php

namespace App\Console\Commands;

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceTextSetting;
use App\Services\MarketplaceTextSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the one row the marketplace cannot run without.
 *
 * migrate does not create the standard plan, and MarketplacePlanSeeder is only
 * reachable through DatabaseSeeder, which builds an entire demo dataset -- demo
 * users, demo workspaces, a demo subscription. Running that against production is
 * not acceptable, which left the plan seeder effectively unreachable on a real
 * deployment.
 *
 * The failure is silent, which is what makes it worth a command. The plans page
 * renders empty, nothing errors, and it can sit unnoticed until somebody tries to
 * pay. This development database is in exactly that state right now: four
 * archived plans, no standard, nothing active.
 *
 * It creates the plan, and tops up any missing settings rows. It creates no users,
 * no workspaces, no posts and nothing else. It never overwrites a value an admin
 * has edited -- an existing plan is left exactly as it is, prices included.
 *
 * The settings and text-settings tables seed themselves in their own migrations,
 * so those are verified rather than created. Re-creating them here would be a
 * second source of truth for rows that already have one.
 */
class MarketplaceInstall extends Command
{
    protected $signature = 'marketplace:install
                            {--dry-run : Report what would be created and write nothing}';

    protected $description = 'Create the standard marketplace plan and top up default settings, without demo data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('DRY RUN — nothing will be written.');
            $this->newLine();
        }

        $planCreated = $this->ensurePlan($dryRun);
        $settingsAdded = $this->topUpSettings($dryRun);
        $missingText = $this->missingTextSettingKeys();

        if ($planCreated) {
            $this->components->twoColumnDetail('Plan', $dryRun ? 'standard would be created' : 'standard created');
        }

        $this->components->twoColumnDetail(
            'Free settings',
            $settingsAdded === 0
                ? 'all present'
                : ($dryRun ? "{$settingsAdded} would be added" : "{$settingsAdded} added")
        );

        $this->components->twoColumnDetail(
            'Payment instruction rows',
            $missingText === [] ? 'all present' : count($missingText).' missing'
        );

        $this->newLine();

        $active = MarketplacePlan::query()->where('is_active', true)->count();

        if ($active === 0) {
            $this->components->error('No active plan. The plans page will render empty and nothing will error.');

            return self::FAILURE;
        }

        if ($missingText !== []) {
            $this->components->warn('Payment instruction rows are missing keys: '.implode(', ', $missingText));
            $this->components->warn('Their migration seeds them; check that it ran.');
        }

        $this->components->info(sprintf('%d active plan(s). Marketplace is ready.', $active));

        return self::SUCCESS;
    }

    /**
     * The standard plan, if it is not already there.
     *
     * Exists rather than updateOrCreate on purpose: an admin may have set a price,
     * and re-running an install must never silently revert it. An existing plan
     * is left alone entirely, archived or not, because un-archiving is a decision
     * for a person and not for an installer.
     */
    private function ensurePlan(bool $dryRun): bool
    {
        if (MarketplacePlan::withTrashed()->where('slug', 'standard')->exists()) {
            $this->components->twoColumnDetail('Plan', 'standard already exists — left as is');

            return false;
        }

        $data = config('marketplace.plans.standard');

        if (! is_array($data) || $data === []) {
            $this->components->error('config/marketplace.php has no plans.standard entry.');

            return false;
        }

        if (! $dryRun) {
            MarketplacePlan::create([
                'slug' => 'standard',
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price_weekly_etb' => $data['price_weekly_etb'] ?? 0,
                'price_monthly_etb' => $data['price_monthly_etb'] ?? 0,
                'price_yearly_etb' => $data['price_yearly_etb'] ?? 0,
                'is_active' => true,
                'is_popular' => $data['popular'] ?? false,
                'sort_order' => $data['sort_order'] ?? 1,
                'limits' => $data['limits'] ?? [],
                'features' => $data['features'] ?? [],
                'faq' => $data['faq'] ?? [],
            ]);
        }

        return true;
    }

    /**
     * Top up any missing Free-quota and period rows.
     *
     * Their migration seeds these, so this is belt and braces for a database
     * where it did not run or where a row was deleted by hand. firstOrCreate
     * semantics: an existing row is never written to, so an admin-edited limit
     * survives.
     *
     * @return int how many were missing
     */
    private function topUpSettings(bool $dryRun): int
    {
        if (! Schema::hasTable('marketplace_settings')) {
            return 0;
        }

        $defaults = config('marketplace.free_limits', []);

        foreach (config('marketplace.free_settings', []) as $key) {
            $defaults[$key] ??= config("marketplace.{$key}");
        }

        $missing = 0;

        foreach ($defaults as $key => $value) {
            if ($value === null || MarketplaceSetting::where('key', $key)->exists()) {
                continue;
            }

            if (! $dryRun) {
                MarketplaceSetting::create(['key' => $key, 'value' => $value]);
            }

            $missing++;
        }

        return $missing;
    }

    /**
     * Which payment-instruction keys are absent.
     *
     * @return list<string>
     */
    private function missingTextSettingKeys(): array
    {
        if (! Schema::hasTable('marketplace_text_settings')) {
            return ['<table missing>'];
        }

        $required = collect(MarketplaceTextSettingsService::REQUIRED_FIELDS)
            ->flatMap(fn (array $fields): array => $fields)
            ->push('manual_payment_instructions')
            ->unique()
            ->all();

        $present = MarketplaceTextSetting::query()->pluck('key')->all();

        return array_values(array_diff($required, $present));
    }
}
