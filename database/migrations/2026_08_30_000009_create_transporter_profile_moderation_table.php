<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_profile_moderation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_profile_moderation');
    }
};
