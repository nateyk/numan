<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing installations with duplicate connections must resolve
        // those conflicting agreements before applying this migration.
        // Do not silently delete historical connection or audit data.
        Schema::table('marketplace_connections', function (Blueprint $table): void {
            $table->unique('freight_post_id', 'marketplace_connections_post_unique');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table): void {
            $table->dropUnique('marketplace_connections_post_unique');
        });
    }
};
