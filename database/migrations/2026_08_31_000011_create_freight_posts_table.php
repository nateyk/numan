<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_posts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->decimal('origin_exact_lat', 10, 7)->nullable();
            $table->decimal('origin_exact_lng', 10, 7)->nullable();
            $table->decimal('destination_exact_lat', 10, 7)->nullable();
            $table->decimal('destination_exact_lng', 10, 7)->nullable();

            $table->text('origin_site_note')->nullable();
            $table->text('destination_site_note')->nullable();
            $table->timestamp('origin_point_confirmed_at')->nullable();
            $table->timestamp('destination_point_confirmed_at')->nullable();

            $table->foreignId('cargo_type_id')->nullable()->constrained('cargo_types')->nullOnDelete();
            $table->text('cargo_description')->nullable();

            $table->foreignId('required_truck_type_id')->nullable()->constrained('truck_types')->nullOnDelete();
            $table->unsignedInteger('truck_count')->default(1);

            $table->string('schedule_type')->default('one_time');
            $table->date('pickup_date')->nullable();
            $table->time('pickup_time')->nullable();
            $table->unsignedTinyInteger('pickup_date_flexibility_days')->default(0);

            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('completion_score')->default(0);

            $table->timestamps();

            $table->index(['status']);
            $table->index(['origin_location_id']);
            $table->index(['destination_location_id']);
            $table->index(['pickup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_posts');
    }
};
