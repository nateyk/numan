<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consignors can share a WhatsApp number too.
 *
 * public_profiles already carried whatsapp_visible, but no number to publish,
 * so a consignor had no WhatsApp channel at all. The transporter profile keeps
 * its own column; this one is preferred by ContactRevealService and falls back
 * to the transporter value for users who filled that form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->string('whatsapp')->nullable()->after('phone');
        });

        if (! Schema::hasTable('transporter_profiles')) {
            return;
        }

        // Chunked rather than a joined UPDATE: the three supported drivers
        // compile joined updates differently, and this migration runs on SQLite
        // in tests and MySQL or Postgres in production.
        DB::table('public_profiles')
            ->whereNull('whatsapp')
            ->select(['id', 'user_id'])
            ->chunkById(100, function ($profiles): void {
                $numbers = DB::table('transporter_profiles')
                    ->whereIn('user_id', $profiles->pluck('user_id'))
                    ->whereNotNull('whatsapp')
                    ->pluck('whatsapp', 'user_id');

                foreach ($profiles as $profile) {
                    $number = $numbers[$profile->user_id] ?? null;

                    if (filled($number)) {
                        DB::table('public_profiles')
                            ->where('id', $profile->id)
                            ->update(['whatsapp' => $number]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->dropColumn('whatsapp');
        });
    }
};
