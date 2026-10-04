<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds provider = 'chapa' to every existing payment transaction.
 *
 * Split from the column migration so the backfill can be asserted before any
 * index depends on it. If this runs and leaves a null behind, every later
 * provider filter silently treats that row as invisible.
 *
 * Existing rows are, by definition, all Chapa: manual payment does not exist
 * yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_payment_transactions')) {
            return;
        }

        DB::table('marketplace_payment_transactions')
            ->whereNull('provider')
            ->update(['provider' => 'chapa']);

        $remaining = DB::table('marketplace_payment_transactions')
            ->whereNull('provider')
            ->count();

        if ($remaining > 0) {
            // Abort rather than continue. A partially backfilled provider column
            // would make some historical rows disappear from the Chapa list and,
            // worse, from reconciliation.
            throw new RuntimeException(
                "Provider backfill incomplete: {$remaining} row(s) still have a null provider. "
                .'Refusing to add the column on a partially migrated table.'
            );
        }
    }

    public function down(): void
    {
        // Nothing to undo. The backfill is idempotent and the column's default
        // keeps new rows correct even if this migration is rolled back.
    }
};
