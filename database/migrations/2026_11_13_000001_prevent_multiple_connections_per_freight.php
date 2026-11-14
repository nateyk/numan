<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check before applying the index. A raw SQL duplicate-key failure
        // gives operators no practical route to repair older accepted rows.
        // Never delete a winning/losing agreement without human review.
        $duplicates = DB::table('marketplace_connections')
            ->select('freight_post_id')
            ->selectRaw('COUNT(*) AS connection_count')
            ->groupBy('freight_post_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('freight_post_id')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sampleIds = $duplicates->pluck('freight_post_id')->implode(', ');

            throw new RuntimeException(
                "Conflicting accepted connections exist for freight post IDs {$sampleIds}. "
                .'Run php artisan freight:check-connections to inspect them, '
                .'then resolve historical agreements manually before running migrations. '
                .'No connection records have been deleted.'
            );
        }

        Schema::table('marketplace_connections', function (Blueprint $table): void {
            $table->unique('freight_post_id', 'marketplace_connections_post_unique');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table): void {
            $table->dropUnique('marketplace_connections_post_unique');
        });
    }
};
