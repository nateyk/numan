<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table) {
            $table->string('billing_interval')->nullable()->after('chapa_ref');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_payment_transactions', function (Blueprint $table) {
            $table->dropColumn('billing_interval');
        });
    }
};
