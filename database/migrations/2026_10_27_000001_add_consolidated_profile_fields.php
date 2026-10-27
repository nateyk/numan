<?php

use App\Services\ProfileConsolidation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the consolidated profile columns and absorb transporter_profiles.
 *
 * The legacy table is left in place until the drop migration that ships in the
 * same release. This migration is additive and backfills every existing
 * transporter row onto its public profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function (Blueprint $table): void {
            // Nullable here, unique only after the backfill has filled every row.
            $table->string('slug')->nullable();
            $table->string('email')->nullable();
            $table->text('business_description')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->text('verification_note')->nullable();
            $table->timestamp('verification_requested_at')->nullable();
            $table->timestamp('verification_reviewed_at')->nullable();
            $table->unsignedTinyInteger('completion_score')->default(0);
        });

        Schema::create('profile_truck_type', function (Blueprint $table): void {
            $table->foreignId('profile_id')->constrained('public_profiles')->cascadeOnDelete();
            $table->foreignId('truck_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['profile_id', 'truck_type_id']);
        });

        Schema::create('profile_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('public_profiles')->cascadeOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['profile_id']);
            $table->unique(['profile_id', 'origin_location_id', 'destination_location_id'], 'profile_routes_unique');
        });

        Schema::create('profile_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('public_profiles')->cascadeOnDelete();
            $table->string('type');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id']);
            $table->index(['status']);
            $table->index(['type']);
        });

        Schema::create('profile_moderations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('public_profiles')->cascadeOnDelete();
            $table->string('kind');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['profile_id']);
        });

        app(ProfileConsolidation::class)->backfill();

        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->unique('slug');
            $table->index('status');
            $table->index('verification_status');
            $table->index(['provides_transport', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_moderations');
        Schema::dropIfExists('profile_documents');
        Schema::dropIfExists('profile_routes');
        Schema::dropIfExists('profile_truck_type');

        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropIndex(['status']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['provides_transport', 'status']);
        });

        Schema::table('public_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'slug',
                'email',
                'business_description',
                'status',
                'verification_status',
                'verification_note',
                'verification_requested_at',
                'verification_reviewed_at',
                'completion_score',
            ]);
        });
    }
};
