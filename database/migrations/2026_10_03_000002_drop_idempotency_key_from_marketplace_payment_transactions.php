<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop marketplace_payment_transactions.idempotency_key.
 *
 * The column was written on every checkout as a fresh Str::ulid() and never
 * read anywhere. Because the value is generated per attempt, a double submit
 * produces two *different* keys, so the unique constraint never fires and the
 * column prevented nothing. Its presence also implied a guarantee that did not
 * exist.
 *
 * Real double-grant protection is provided by:
 *   - unique index on marketplace_subscriptions.chapa_tx_ref
 *   - unique index on marketplace_payment_receipts.transaction_id
 *   - MarketplaceFulfillmentService re-checking status under lockForUpdate()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('billing_interval');
        });
    }
};
