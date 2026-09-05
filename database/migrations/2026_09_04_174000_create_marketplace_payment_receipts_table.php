<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('marketplace_payment_transactions')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('marketplace_subscriptions')->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_etb', 10, 2);
            $table->string('currency')->default('ETB');
            $table->string('invoice_number')->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_receipts');
    }
};
