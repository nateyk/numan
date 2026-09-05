<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('marketplace_plans')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tx_ref')->unique();
            $table->string('chapa_ref')->nullable()->index();
            $table->decimal('amount_etb', 10, 2);
            $table->string('currency')->default('ETB');
            $table->string('status')->default('pending'); // pending, initialized, verified, failed
            $table->string('mode')->default('test'); // test, live
            $table->json('provider_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_transactions');
    }
};
