<?php

use App\Models\Profile;
use App\Models\TransporterProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Let anybody decline to publish their phone, not only transporters.
 *
 * ContactRevealService decided whether to suppress a phone number with
 *
 *     $transporter !== null && ! $transporter->phone_visible
 *
 * so the refusal flag only existed on transporter_profiles. A consignor has no
 * transporter row, so the expression was false, so their number was always
 * published — and the reveal ledger logged the release as though it had been
 * agreed. A consignor had no way to say no at all.
 *
 * The flags move to profiles, which is the record that holds identity for every
 * kind of user, and the reveal reads them from there.
 *
 * Defaults to true, matching the historical behaviour for everybody: this
 * migration must not start hiding numbers that were being released yesterday. The
 * backfill below then re-applies the opt-outs transporters had already made, so
 * somebody who deliberately hid their number does not have it re-published by a
 * schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function ($table): void {
            foreach (['phone_visible', 'whatsapp_visible', 'email_visible'] as $column) {
                $table->boolean($column)->default(true);
            }
        });

        $this->carryOverExistingRefusals();
    }

    /**
     * Copy each transporter's existing choices onto their profile.
     *
     * Only where a transporter row exists. A consignor has no row to copy from
     * and keeps the default of true, which is what happened before this change.
     */
    private function carryOverExistingRefusals(): void
    {
        if (! Schema::hasTable('transporter_profiles')) {
            return;
        }

        TransporterProfile::query()
            ->select(['id', 'user_id', 'phone_visible', 'whatsapp_visible', 'email_visible'])
            ->chunkById(200, function ($transporterProfiles): void {
                foreach ($transporterProfiles as $transporter) {
                    $profile = Profile::where('user_id', $transporter->user_id)->first();

                    if (! $profile) {
                        continue;
                    }

                    $profile->forceFill([
                        'phone_visible' => (bool) $transporter->phone_visible,
                        'whatsapp_visible' => (bool) $transporter->whatsapp_visible,
                        'email_visible' => (bool) $transporter->email_visible,
                    ])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('public_profiles', function ($table): void {
            $table->dropColumn(['phone_visible', 'whatsapp_visible', 'email_visible']);
        });

        // The transporter columns were never removed, so rolling back restores the
        // previous behaviour exactly: the reveal service goes back to reading them,
        // and nobody loses a preference they had already set.
    }
};
