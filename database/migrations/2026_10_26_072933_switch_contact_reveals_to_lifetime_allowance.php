<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move contact reveals from a rolling weekly quota to a one-time allowance.
 *
 * Free accounts now get five reveals in total. The reveal window setting is
 * gone: nothing refills the allowance with time, and re-revealing an existing
 * partner remains free forever through the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_settings')) {
            $now = now();
            $old = DB::table('marketplace_settings')
                ->where('key', 'free_contact_reveals_per_week')
                ->first();

            DB::table('marketplace_settings')->where('key', 'free_contact_reveals')->delete();

            if ($old !== null) {
                DB::table('marketplace_settings')
                    ->where('id', $old->id)
                    ->update([
                        'key' => 'free_contact_reveals',
                        'value' => 5,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('marketplace_settings')->insert([
                    'key' => 'free_contact_reveals',
                    'value' => 5,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('marketplace_settings')
                ->where('key', 'free_contact_reveal_window_days')
                ->delete();
        }

        if (! Schema::hasTable('marketplace_plans')) {
            return;
        }

        foreach (DB::table('marketplace_plans')->get() as $plan) {
            $limits = json_decode($plan->limits ?? '[]', true);

            if (! is_array($limits)) {
                $limits = [];
            }

            if (array_key_exists('contact_reveals_per_week', $limits)) {
                $limits['contact_reveals'] = $limits['contact_reveals_per_week'];
                unset($limits['contact_reveals_per_week']);
            }

            unset($limits['contact_reveal_window_days']);

            // Standard is the unlimited plan. If a row somehow missed the old
            // key, stamp the new one so a paid subscriber never inherits the
            // free five.
            if ($plan->slug === 'standard' && ! array_key_exists('contact_reveals', $limits)) {
                $limits['contact_reveals'] = -1;
            }

            DB::table('marketplace_plans')->where('id', $plan->id)->update([
                'limits' => json_encode($limits),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_settings')) {
            $now = now();
            $old = DB::table('marketplace_settings')
                ->where('key', 'free_contact_reveals')
                ->first();

            DB::table('marketplace_settings')
                ->where('key', 'free_contact_reveals_per_week')
                ->delete();

            if ($old !== null) {
                DB::table('marketplace_settings')
                    ->where('id', $old->id)
                    ->update([
                        'key' => 'free_contact_reveals_per_week',
                        'value' => 3,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('marketplace_settings')->insert([
                    'key' => 'free_contact_reveals_per_week',
                    'value' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('marketplace_settings')->updateOrInsert(
                ['key' => 'free_contact_reveal_window_days'],
                ['value' => 7, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        if (! Schema::hasTable('marketplace_plans')) {
            return;
        }

        foreach (DB::table('marketplace_plans')->get() as $plan) {
            $limits = json_decode($plan->limits ?? '[]', true);

            if (! is_array($limits)) {
                $limits = [];
            }

            if (array_key_exists('contact_reveals', $limits)) {
                $limits['contact_reveals_per_week'] = $limits['contact_reveals'];
                unset($limits['contact_reveals']);
            }

            DB::table('marketplace_plans')->where('id', $plan->id)->update([
                'limits' => json_encode($limits),
                'updated_at' => now(),
            ]);
        }
    }
};
