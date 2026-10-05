<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Raise the Free plan live-post limit from 1 to 5.
     *
     * Only rows still on the old default are touched: an admin who already
     * customized the value keeps their setting. Environments without the row
     * fall through to the config default (also 5 now).
     */
    public function up(): void
    {
        $updated = DB::table('marketplace_settings')
            ->where('key', 'free_active_posts')
            ->where('value', 1)
            ->update(['value' => 5, 'updated_at' => now()]);

        if ($updated === 0 && ! DB::table('marketplace_settings')->where('key', 'free_active_posts')->exists()) {
            DB::table('marketplace_settings')->insert([
                'key' => 'free_active_posts',
                'value' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('marketplace_settings')
            ->where('key', 'free_active_posts')
            ->where('value', 5)
            ->update(['value' => 1, 'updated_at' => now()]);
    }
};
