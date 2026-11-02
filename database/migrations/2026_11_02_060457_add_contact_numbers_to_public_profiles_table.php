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
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->json('phone_numbers')->nullable();
            $table->json('whatsapp_numbers')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->dropColumn(['phone_numbers', 'whatsapp_numbers']);
        });
    }
};
