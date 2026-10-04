<?php

namespace App\Console\Commands;

use App\Services\ManualPaymentExpiryService;
use Illuminate\Console\Command;

/**
 * Lapses manual payment requests nobody reviewed.
 *
 * DISABLED BY DEFAULT, same flag as marketplace:expire-subscriptions. It writes
 * to real data and sends real notifications, so it is not scheduled unless
 * MARKETPLACE_EXPIRY_ENABLED is truthy. --dry-run always works.
 */
class ExpireManualPayments extends Command
{
    protected $signature = 'marketplace:expire-manual-payments
                            {--dry-run : Report what would lapse without writing}';

    protected $description = 'Close unreviewed manual payment requests past their expiry window';

    public function handle(ManualPaymentExpiryService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->line("Manual payment review window: {$service->windowDays()} day(s).");
        $this->newLine();

        $result = $service->run($dryRun);

        if ($result['rows'] === []) {
            $this->info('No manual payment requests are past their review window.');

            return self::SUCCESS;
        }

        $this->table(
            ['Ref', 'Workspace', 'Plan', 'Amount', 'Method', 'Submitted', 'Lapsed'],
            array_map(fn (array $row): array => [
                $row['tx_ref'],
                $row['workspace_id'],
                $row['plan'],
                $row['amount_etb'].' ETB',
                $row['method'] ?? '-',
                $row['submitted_at'] ?? '-',
                $row['expired_at'] ?? '-',
            ], $result['rows']),
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn("Dry run. {$result['examined']} request(s) would lapse. Nothing was written.");

            return self::SUCCESS;
        }

        $this->info("{$result['expired']} request(s) lapsed and {$result['notified']} payer(s) notified.");
        $this->line('No plan was activated and no payment was recorded. Expired requests can be reopened by an admin.');

        return self::SUCCESS;
    }
}
