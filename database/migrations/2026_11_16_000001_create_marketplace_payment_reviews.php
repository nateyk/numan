<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_payment_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained('marketplace_payment_transactions')->restrictOnDelete();
            $table->string('reason', 50);
            $table->string('status', 40)->default('open');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_reference', 100)->nullable();
            $table->timestamp('provider_reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('marketplace_payment_review_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('marketplace_payment_reviews')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('provider_reference', 100)->nullable();
            $table->timestamp('created_at');
            $table->index(['review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payment_review_events');
        Schema::dropIfExists('marketplace_payment_reviews');
    }
};
