<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connections', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('freight_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freight_interest_id')->constrained('freight_interests')->cascadeOnDelete();
            $table->foreignId('consignor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('freight_interest_id');
            $table->index(['consignor_user_id', 'transporter_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
