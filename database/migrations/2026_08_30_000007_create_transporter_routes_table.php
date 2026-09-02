<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->unique(['transporter_profile_id', 'origin_location_id', 'destination_location_id'], 'transporter_routes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_routes');
    }
};
