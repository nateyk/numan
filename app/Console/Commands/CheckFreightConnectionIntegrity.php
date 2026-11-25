<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only preflight for the one-connection-per-freight database constraint.
 *
 * No connections or historical audit records are changed by this command.
 */
class CheckFreightConnectionIntegrity extends Command
{
    protected $signature = 'freight:check-connections {--limit=20 : Maximum conflicting freight records to display}';

    protected $description = 'Find freight posts with competing accepted connections before applying the unique index';

    public function handle(): int
    {
        if (Schema::hasTable('marketplace_connections') === false) {
            $this->info('No connection records exist yet. The integrity check passed.');

            return self::SUCCESS;
        }

        $limit = min(100, max(1, (int) $this->option('limit')));

        $duplicates = DB::table('marketplace_connections')
            ->select('freight_post_id')
            ->selectRaw('COUNT(*) AS connection_count')
            ->groupBy('freight_post_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('freight_post_id')
            ->limit($limit + 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No freight post has more than one accepted connection. Safe to add the unique index.');

            return self::SUCCESS;
        }

        $truncated = $duplicates->count() > $limit;

        $rows = $duplicates->take($limit)->map(function ($group): array {
            return [
                $group->freight_post_id,
                $group->connection_count,
                DB::table('marketplace_connections')
                    ->where('freight_post_id', $group->freight_post_id)
                    ->orderBy('id')
                    ->pluck('id')
                    ->implode(', '),
            ];
        })->all();

        $this->error('Conflicting accepted connections found. No data was changed.');
        $this->table(['Freight post ID', 'Connections', 'Connection row IDs'], $rows);

        if ($truncated) {
            $this->warn('More conflicting posts exist. Rerun with --limit=100.');
        }

        $this->warn('Review the real freight agreements and their audit histories; resolve conflicts manually before migrating.');

        return self::FAILURE;
    }
}
