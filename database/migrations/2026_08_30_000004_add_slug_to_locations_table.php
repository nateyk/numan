<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('locations', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        // Backfill slug for existing rows
        $locations = DB::table('locations')->whereNull('slug')->orWhere('slug', '')->get();
        foreach ($locations as $loc) {
            $slug = Str::slug($loc->name.'-'.$loc->region_name);
            $base = $slug;
            $i = 1;
            while (DB::table('locations')->where('slug', $slug)->where('id', '!=', $loc->id)->exists()) {
                $slug = $base.'-'.$i++;
            }
            DB::table('locations')->where('id', $loc->id)->update(['slug' => $slug]);
        }

        Schema::table('locations', function (Blueprint $table): void {
            // Make slug unique via index if not already
            try {
                $table->unique('slug');
            } catch (Throwable $e) {
                // Index may already exist
            }
            try {
                $table->index(['name', 'region_name']);
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
