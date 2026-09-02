<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Step 1 — capabilities (multi-select)
            $table->boolean('posts_freight')->default(false);
            $table->boolean('provides_transport')->default(false);

            // Step 2 — account type
            $table->string('account_type')->nullable()->comment('personal, business');

            // Step 3 — details
            $table->string('display_name')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('primary_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('legal_name')->nullable()->comment('Business legal name, e.g. ABC Transport PLC');
            $table->string('legal_form')->nullable()->comment('PLC, SC, Sole Proprietorship, etc or Other');
            $table->string('legal_form_other')->nullable()->comment('Free text when legal_form=Other');

            // Onboarding state
            $table->string('onboarding_step')->nullable()->comment('capabilities, account_type, details, completed');
            $table->timestamp('onboarding_completed_at')->nullable();

            $table->timestamps();

            $table->index(['account_type']);
            $table->index(['onboarding_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_profiles');
    }
};
