<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('status');

            $table->index(
                ['workspace_id', 'hidden_at'],
                'freight_posts_workspace_hidden_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->dropIndex('freight_posts_workspace_hidden_index');
            $table->dropColumn('hidden_at');
        });
    }
};
