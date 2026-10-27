<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The onboarding wizard is gone, so its progress columns have no reader.
 *
 * The profile itself is the single record now; completion is derived from
 * readiness rather than a stored step. down() can restore the columns but not
 * the progress they used to hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->dropIndex(['onboarding_step']);
            $table->dropColumn(['onboarding_step', 'onboarding_completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->string('onboarding_step')->nullable()->comment('capabilities, account_type, details, completed');
            $table->timestamp('onboarding_completed_at')->nullable();

            $table->index(['onboarding_step']);
        });
    }
};
