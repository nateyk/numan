<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How long an admin has to review a manual payment before it lapses.
 *
 * Seeded here rather than in the text settings table because it is a number and
 * marketplace_settings is the integer table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('marketplace_settings')->insertOrIgnore([
            'key' => 'free_manual_payment_expiry_days',
            'value' => (int) config('marketplace.free_manual_payment_expiry_days', 14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('marketplace_settings')->where('key', 'free_manual_payment_expiry_days')->delete();
    }
};
