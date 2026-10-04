<?php

namespace App\Console\Commands;

use App\Services\BackfillContactRevealsService;
use Illuminate\Console\Command;

/**
 * Seeds the contact-reveal ledger for relationships that already existed.
 *
 * Run this once, immediately after deploying the metering code and BEFORE
 * enabling anything that reveals contacts to a paying user, otherwise people who
 * already had a counterparty's number find themselves locked out of it.
 *
 * Safe to run more than once: the unique (revealer_id, revealed_id) key means a
 * pair that already exists is skipped, not duplicated.
 */
class BackfillContactReveals extends Command
{
    protected $signature = 'marketplace:backfill-contact-reveals
                            {--dry-run : Report what would be written without writing it}';

    protected $description = 'Seed the contact_reveals ledger from existing connections and accepted interests';

    public function handle(BackfillContactRevealsService $service): int
    {
        if ($this->option('dry-run')) {
            $this->report($service->preview());

            return self::SUCCESS;
        }

        $result = $service->run();

        $this->report($result);

        $this->newLine();
        $this->info('Ledger seeded. Existing counterparties keep the access they already had.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function report(array $counts): void
    {
        $this->table(
            ['Measure', 'Count'],
            [
                ['Connections examined', (int) ($counts['connections'] ?? 0)],
                ['Accepted interests examined', (int) ($counts['interests'] ?? 0)],
                ['Ledger rows '.(($this->option('dry-run')) ? 'that would be written' : 'written'), (int) ($counts['rows_created'] ?? 0)],
                ['Pairs already recorded, skipped', (int) ($counts['pairs_skipped'] ?? 0)],
            ],
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run. Nothing was written.');
        }
    }
}
