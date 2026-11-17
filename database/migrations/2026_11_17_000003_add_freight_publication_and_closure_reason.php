<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            // A draft can exist for days before its first public approval.
            $table->timestamp('published_at')->nullable();
            // Closing because transport was arranged differs from cancellation.
            // Null preserves the unknown outcome of existing closed posts.
            $table->string('closure_reason', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            $table->dropColumn(['published_at', 'closure_reason']);
        });
    }
};
