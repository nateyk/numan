<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_profile_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status')->default('pending')->comment('pending,approved,rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_profile_revisions');
    }
};
