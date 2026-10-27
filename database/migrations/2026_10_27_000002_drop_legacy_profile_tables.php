<?php

use App\Services\ProfileConsolidation;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove the legacy transporter schema now that every reader uses Profile.
 *
 * This ships in the same release as the additive backfill migration; there is no
 * deployable state between them. down() recreates the old tables and copies the
 * consolidated data back rather than refusing to run.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ProfileConsolidation::class)->dropLegacy();
    }

    public function down(): void
    {
        app(ProfileConsolidation::class)->restoreLegacy();
    }
};
