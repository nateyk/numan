<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapa_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            // Deduplicate an exact signed retry but retain different partial
            // refunds even when tx_ref and event name are identical.
            $table->char('payload_hash', 64)->unique();
            $table->string('tx_ref')->index();
            $table->string('event');
            $table->string('webhook_type');
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapa_webhook_deliveries');
    }
};
