<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->string('legal_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('business_description')->nullable();
            $table->boolean('is_company')->default(false);
            $table->string('status')->default('draft')->comment('draft,pending,approved,rejected');
            $table->string('verification_level')->default('unverified')->comment('unverified,verified');
            $table->integer('completion_score')->default(0);
            $table->boolean('phone_visible')->default(false);
            $table->boolean('whatsapp_visible')->default(false);
            $table->boolean('email_visible')->default(false);
            $table->foreignId('primary_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['verification_level']);
            $table->index(['is_company']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_profiles');
    }
};
