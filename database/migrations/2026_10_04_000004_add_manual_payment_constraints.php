<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The constraints that make manual payment safe under concurrency.
 *
 * Two, both unique, both doing work a controller check cannot:
 *
 *  1. (manual_method, manual_reference). Dedupe on what the payer typed. NULLs
 *     are distinct in SQLite, MySQL and PostgreSQL, so every Chapa row -- both
 *     columns null -- coexists freely. No sentinel value and no generated
 *     column needed, because a NULL never equals a NULL in a unique index.
 *
 *  2. open_manual_workspace_id. One open request per workspace. The column
 *     holds the workspace id while a request is pending_confirmation and is
 *     null on every other outcome, so the unique index only ever sees open
 *     rows. A second submit from the same workspace fails on the index rather
 *     than on a read-then-write race.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table): void {
            // Both columns are nullable, so this is a partial index in effect:
            // only rows that actually carry a method and a reference are
            // constrained.
            $table->unique(
                ['manual_method', 'manual_reference'],
                'marketplace_transactions_manual_dedupe_unique'
            );

            $table->unique(
                'open_manual_workspace_id',
                'marketplace_transactions_one_open_manual_unique'
            );

            $table->index(['provider', 'status'], 'marketplace_transactions_provider_status_index');

            $table->foreign('manual_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table): void {
            $table->dropUnique('marketplace_transactions_manual_dedupe_unique');
            $table->dropUnique('marketplace_transactions_one_open_manual_unique');
            $table->dropIndex('marketplace_transactions_provider_status_index');
        });
    }
};
