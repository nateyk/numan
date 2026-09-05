<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('marketplace_plans')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('trialing'); // trialing, active, past_due, canceled
            $table->string('billing_interval')->nullable(); // monthly, yearly
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('chapa_tx_ref')->nullable()->index();
            $table->string('provider')->default('chapa'); // chapa, stripe
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_subscriptions');
    }
};
