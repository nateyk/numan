<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('freight_posts', 'origin_site_notes')) {
                $table->dropColumn('origin_site_notes');
            }

            if (Schema::hasColumn('freight_posts', 'destination_site_notes')) {
                $table->dropColumn('destination_site_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('freight_posts', 'origin_site_notes')) {
                $table->text('origin_site_notes')->nullable();
            }

            if (! Schema::hasColumn('freight_posts', 'destination_site_notes')) {
                $table->text('destination_site_notes')->nullable();
            }
        });
    }
};
