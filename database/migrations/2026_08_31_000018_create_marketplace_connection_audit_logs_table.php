<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connection_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_connection_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connection_audit_logs');
    }
};
