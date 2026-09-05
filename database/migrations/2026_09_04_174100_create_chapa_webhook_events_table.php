<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapa_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event')->nullable();
            $table->string('tx_ref')->index();
            $table->string('chapa_ref')->nullable()->index();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['tx_ref', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapa_webhook_events');
    }
};
