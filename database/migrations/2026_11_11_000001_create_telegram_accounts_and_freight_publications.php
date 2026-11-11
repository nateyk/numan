<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('chat_id', 64)->unique();
            $table->timestamps();
        });

        Schema::table('freight_posts', function (Blueprint $table): void {
            $table->timestamp('telegram_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            $table->dropColumn('telegram_published_at');
        });

        Schema::dropIfExists('telegram_accounts');
    }
};
