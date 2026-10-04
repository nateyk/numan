<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The manual payment columns.
 *
 * Manual payments live in marketplace_payment_transactions rather than a table
 * of their own, so that confirming one goes through
 * MarketplaceFulfillmentService::fulfill() -- the same single path Chapa uses.
 * Renewal-extends, ends_at, hidden_at restore and the receipt all come from
 * there. A second table would mean a second fulfilment path, which is the thing
 * this codebase deliberately does not have.
 *
 * plan_id, billing_interval and amount_etb already exist as real columns, so the
 * price agreed at submission is structurally safe from later price changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table): void {
            // Which rail moved the money. Load-bearing: chapa:reconcile selects
            // on status alone, so without this a manual row would be sent to
            // Chapa's verify API.
            $table->string('provider', 20)->default('chapa')->after('mode');

            // Which rail the payer used, and what they typed into their banking
            // app. NOT the same as tx_ref, which is our own NF code.
            $table->string('manual_method', 40)->nullable()->after('provider');
            $table->string('manual_reference', 120)->nullable()->after('manual_method');
            $table->string('manual_payer_phone', 30)->nullable()->after('manual_reference');
            $table->string('manual_proof_path')->nullable()->after('manual_payer_phone');

            $table->timestamp('manual_submitted_at')->nullable()->after('manual_proof_path');

            // Who confirmed or rejected, and when. An approval decision needs an
            // accountable human attached to it.
            $table->foreignId('manual_reviewed_by')->nullable()->after('manual_submitted_at');
            $table->timestamp('manual_reviewed_at')->nullable()->after('manual_reviewed_by');
            $table->text('manual_rejection_reason')->nullable()->after('manual_reviewed_at');

            $table->timestamp('manual_expires_at')->nullable()->after('manual_rejection_reason');

            // Enforces "one open request per workspace" in the database rather
            // than in a controller check, because two concurrent submits would
            // both pass a read-then-write test. Set to the workspace id while
            // pending_confirmation and nulled on every other outcome, so the
            // unique index only ever sees the open rows.
            $table->unsignedBigInteger('open_manual_workspace_id')->nullable()->after('manual_expires_at');
        });

        // Re-run the backfill for rows created between the two migrations, then
        // assert it before anything depends on it.
        DB::table('marketplace_payment_transactions')
            ->whereNull('provider')
            ->update(['provider' => 'chapa']);

        $nulls = DB::table('marketplace_payment_transactions')->whereNull('provider')->count();

        if ($nulls > 0) {
            throw new RuntimeException("{$nulls} payment transaction(s) still have a null provider.");
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'provider',
                'manual_method',
                'manual_reference',
                'manual_payer_phone',
                'manual_proof_path',
                'manual_submitted_at',
                'manual_reviewed_by',
                'manual_reviewed_at',
                'manual_rejection_reason',
                'manual_expires_at',
                'open_manual_workspace_id',
            ]);
        });
    }
};
