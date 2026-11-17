<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            // Closing a listing ends matchmaking; arrival is a separate
            // operational milestone. Existing closed posts remain untouched.
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            $table->dropColumn(['closed_at', 'arrived_at']);
        });
    }
};
