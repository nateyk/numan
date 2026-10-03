<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against existing duplicates before adding the constraints.
        // Duplicates are reported, never deleted.
        $this->reportDuplicates();

        Schema::table('marketplace_subscriptions', function (Blueprint $table) {
            // Replace the existing non-unique index with a unique one.
            $table->dropIndex('marketplace_subscriptions_chapa_tx_ref_index');
            $table->unique('chapa_tx_ref');
        });

        Schema::table('marketplace_payment_receipts', function (Blueprint $table) {
            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payment_receipts', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
        });

        Schema::table('marketplace_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['chapa_tx_ref']);
            $table->index('chapa_tx_ref');
        });
    }

    private function reportDuplicates(): void
    {
        if (Schema::hasTable('marketplace_subscriptions')) {
            $dups = DB::table('marketplace_subscriptions')
                ->whereNotNull('chapa_tx_ref')
                ->selectRaw('chapa_tx_ref, COUNT(*) as c')
                ->groupBy('chapa_tx_ref')
                ->havingRaw('c > 1')
                ->get();

            if ($dups->isNotEmpty()) {
                foreach ($dups as $dup) {
                    fwrite(STDERR, "DUPLICATE subscription for chapa_tx_ref {$dup->chapa_tx_ref} ({$dup->c} rows)\n");
                }

                throw new RuntimeException(
                    'Cannot add unique index on marketplace_subscriptions.chapa_tx_ref: '
                    .$dups->count().' duplicate(s) found. Nothing was deleted; resolve these rows manually.'
                );
            }
        }

        if (Schema::hasTable('marketplace_payment_receipts')) {
            $dups = DB::table('marketplace_payment_receipts')
                ->selectRaw('transaction_id, COUNT(*) as c')
                ->groupBy('transaction_id')
                ->havingRaw('c > 1')
                ->get();

            if ($dups->isNotEmpty()) {
                foreach ($dups as $dup) {
                    fwrite(STDERR, "DUPLICATE receipts for transaction_id {$dup->transaction_id} ({$dup->c} rows)\n");
                }

                throw new RuntimeException(
                    'Cannot add unique index on marketplace_payment_receipts.transaction_id: '
                    .$dups->count().' duplicate(s) found. Nothing was deleted; resolve these rows manually.'
                );
            }
        }
    }
};
