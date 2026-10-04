<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds tariff_weight / tariff_weight_unit, and backfills item_name.
 *
 * The tariff weight is the billable weight, which is not always the actual
 * weight: a palletised load can be charged on volume rather than weight, so the
 * rate quote needs both numbers.
 *
 * Renamed from 2026_10_02_000001, which collided with the billing-interval
 * migration already in the tree, and which had already been applied on some
 * environments. The hasColumn guard is therefore load-bearing, not defensive
 * padding: on an environment where the old file ran, these columns exist and
 * adding them again would fail. Everywhere else the columns are created here.
 *
 * The item_name backfill is separate and idempotent. cargo_description is no
 * longer collected, and the completion score now reads item_name, so a
 * historical post that only has the old field would score as missing its cargo.
 * Only rows where item_name is empty are touched, so this can never overwrite
 * something a user has since typed. cargo_description is left in place: it is
 * still what the admin search matches, and dropping a populated column is not
 * this migration's decision to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = array_filter(
            ['tariff_weight', 'tariff_weight_unit'],
            fn (string $column): bool => ! Schema::hasColumn('freight_posts', $column)
        );

        if ($missing !== []) {
            Schema::table('freight_posts', function (Blueprint $table) use ($missing): void {
                if (in_array('tariff_weight', $missing, true)) {
                    $table->decimal('tariff_weight', 10, 2)->nullable()->after('actual_weight');
                }

                if (in_array('tariff_weight_unit', $missing, true)) {
                    $table->string('tariff_weight_unit')->nullable()->after('tariff_weight');
                }
            });
        }

        $this->backfillItemName();
    }

    /**
     * Copy cargo_description into item_name where item_name is empty.
     *
     * Done in PHP rather than SQL because there is no portable substring
     * function: SQLite has no LEFT(), so a raw LEFT() works on MySQL and
     * Postgres and fails outright on the SQLite the test suite runs on.
     * Chunked for the same reason -- this is a table scan on every deploy.
     */
    private function backfillItemName(): void
    {
        $backfilled = 0;

        DB::table('freight_posts')
            ->whereNull('item_name')
            ->whereNotNull('cargo_description')
            ->where('cargo_description', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($posts) use (&$backfilled): void {
                foreach ($posts as $post) {
                    DB::table('freight_posts')
                        ->where('id', $post->id)
                        ->whereNull('item_name')
                        ->update([
                            'item_name' => Str::limit((string) $post->cargo_description, 255, ''),
                            'updated_at' => $post->updated_at ?? now(),
                        ]);

                    $backfilled++;
                }
            });

        if ($backfilled > 0) {
            // Logged rather than echoed. $this->info() only exists when the
            // migrator is driving, so calling it makes the migration
            // unrunnable from a test or a one-off script. The count is the only
            // thing worth keeping, and it is not personal data.
            Log::info('Freight: backfilled item_name from cargo_description', ['posts' => $backfilled]);
        }
    }

    public function down(): void
    {
        // Deliberately empty, and this is not laziness.
        //
        // up() is conditional: on an environment where the old, differently
        // named migration had already run, it creates nothing. A symmetric down()
        // would then drop columns it did not add, which on that environment means
        // dropping a populated column to reverse a no-op. There is no way for a
        // migration to know at rollback time whether it was the thing that added
        // them, so the only safe down() here is none.
        //
        // Dropping the columns by hand is fine if that is ever genuinely wanted.
        // They are nullable, so no other column depends on them.
        //
        // item_name is not cleared either. It is a faithful copy of
        // cargo_description, which is still present, so reverting would lose
        // nothing recoverable but would also gain nothing.
    }
};
