<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freight_interests', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('message');
            $table->string('contact_phone')->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('bank_account_name')->nullable()->after('contact_email');
            $table->string('bank_name')->nullable()->after('bank_account_name');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_iban')->nullable()->after('bank_account_number');
            $table->string('bank_swift')->nullable()->after('bank_iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freight_interests', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name',
                'contact_phone',
                'contact_email',
                'bank_account_name',
                'bank_name',
                'bank_account_number',
                'bank_iban',
                'bank_swift',
            ]);
        });
    }
};
