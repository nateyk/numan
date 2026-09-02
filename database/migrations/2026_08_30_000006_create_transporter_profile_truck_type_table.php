<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_profile_truck_type', function (Blueprint $table): void {
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['transporter_profile_id', 'truck_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_profile_truck_type');
    }
};
