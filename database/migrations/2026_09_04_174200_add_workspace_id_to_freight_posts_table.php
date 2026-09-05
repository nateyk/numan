<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor('workspace_id');
        });
    }
};
