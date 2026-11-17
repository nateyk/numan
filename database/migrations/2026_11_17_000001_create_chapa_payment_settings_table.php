<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapa_payment_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 8)->default('test');
            // Encrypted at the model boundary. The ciphertext columns must
            // remain text: Laravel encrypted values grow unpredictably.
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapa_payment_settings');
    }
};
