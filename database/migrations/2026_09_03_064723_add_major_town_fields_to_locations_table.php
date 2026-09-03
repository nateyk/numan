<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('locations', 'population')) {
                $table->integer('population')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('locations', 'elevation')) {
                $table->integer('elevation')->nullable()->after('population');
            }
            if (! Schema::hasColumn('locations', 'wereda')) {
                $table->string('wereda')->nullable()->after('elevation');
            }
            if (! Schema::hasColumn('locations', 'zone')) {
                $table->string('zone')->nullable()->after('wereda');
            }
            if (! Schema::hasColumn('locations', 'is_major_town')) {
                $table->boolean('is_major_town')->default(false)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['population', 'elevation', 'wereda', 'zone', 'is_major_town']);
        });
    }
};
