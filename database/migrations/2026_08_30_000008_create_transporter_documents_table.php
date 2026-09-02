<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transporter_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('license,insurance,other');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('status')->default('pending')->comment('pending,approved,rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['transporter_profile_id']);
            $table->index(['status']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_documents');
    }
};
