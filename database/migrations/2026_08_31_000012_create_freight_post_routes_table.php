<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_post_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('freight_post_id')->constrained('freight_posts')->cascadeOnDelete();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('geometry')->nullable();
            $table->string('routing_provider')->default('osrm');
            $table->timestamps();

            $table->unique(['freight_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_post_routes');
    }
};
