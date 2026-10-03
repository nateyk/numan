<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSubscription;
use App\Services\MarketplaceListingVisibilityService;
use Illuminate\Console\Command;

class ExpireMarketplaceSubscriptions extends Command
{
    protected $signature = 'marketplace:expire-subscriptions
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Expire lapsed marketplace subscriptions and hide their excess listings';

    public function __construct(
        protected MarketplaceListingVisibilityService $visibility,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('DRY RUN — nothing will be written to the database.');
        }

        $expiredSubscriptions = $dryRun
            ? $this->countLapsedSubscriptions()
            : $this->visibility->expireLapsedSubscriptions();

        if ($dryRun) {
            $this->components->twoColumnDetail(
                'Subscriptions past ends_at',
                "{$expiredSubscriptions} would become 'expired'"
            );
        } else {
            $this->components->twoColumnDetail(
                'Subscriptions marked expired',
                (string) $expiredSubscriptions
            );
        }

        $results = $dryRun
            ? $this->previews()
            : $this->visibility->hideExcessPosts();

        if ($results === []) {
            $this->components->info('No listings need hiding.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Workspaces that would be affected:');
            $this->newLine();
        }

        foreach ($results as $result) {
            $label = sprintf(
                '%s (workspace %d)',
                $result['workspace_name'] ?? 'unknown',
                $result['workspace_id'],
            );

            if ($dryRun) {
                $this->components->twoColumnDetail(
                    $label,
                    sprintf(
                        'plan=%s expired=%s live=%d keep=%s hide=%d',
                        $result['plan'] ?? 'n/a',
                        $result['expired_at'],
                        $result['visible_live_posts'],
                        $result['keep'],
                        $result['would_hide'],
                    )
                );
            } else {
                $this->components->twoColumnDetail(
                    $label,
                    sprintf('hid %d post(s)', $result['hidden'] ? count($result['hide_post_ids']) : 0)
                );
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->warn('Dry run complete. No data was changed.');
        }

        return self::SUCCESS;
    }

    private function countLapsedSubscriptions(): int
    {
        return MarketplaceSubscription::whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->count();
    }

    /**
     * Evaluate every workspace that holds a lapsed subscription.
     *
     * @return list<array<string, mixed>>
     */
    private function previews(): array
    {
        return $this->visibility->previewLapsedWorkspaces();
    }
}
