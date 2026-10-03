<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->integer('value')->default(-1);
            $table->timestamps();
        });

        // Seed defaults so the settings exist even before the seeder runs.
        // Config supplies the fallback values.
        $now = now();

        foreach (config('marketplace.free_limits', []) as $key => $value) {
            DB::table('marketplace_settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings');
    }
};
