<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a locations row be traced back to the OpenStreetMap feature it came from.
 *
 * Location search falls back to Nominatim when the local table has too few
 * matches, which is the normal case for Ethiopia and Djibouti. A user can pick
 * one of those results, and the freight wizard needs a real location_id for it --
 * otherwise origin_location_id is empty, the post cannot be saved, and the
 * public discovery filter has nothing to match on.
 *
 * So a picked OSM result is materialised as a locations row. osm_id is what makes
 * that safe to do repeatedly: Nominatim will return the same feature for the same
 * query forever, and without a unique key on it every search would insert another
 * copy of the same town. The unique index is nullable, so hand-entered rows are
 * all exempt from it and keep working exactly as before.
 *
 * source is informational. is_active defaults to true so a materialised row is
 * immediately usable; an admin can deactivate it later like any other location.
 *
 * down() is empty. Dropping osm_id would orphan every row that was materialised
 * from it, leaving duplicates that nothing can tell apart, which is worse than
 * leaving two nullable columns in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = array_filter(
            ['osm_id', 'source'],
            fn (string $column): bool => ! Schema::hasColumn('locations', $column)
        );

        if ($missing === []) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) use ($missing): void {
            if (in_array('osm_id', $missing, true)) {
                $table->string('osm_id')->nullable()->after('slug');
            }

            if (in_array('source', $missing, true)) {
                $table->string('source')->nullable()->after('osm_id');
            }
        });

        // Added separately because a unique index has to tolerate the many rows
        // that have no OSM id at all. Nullable unique columns allow unlimited
        // NULLs on every database this runs on.
        if (in_array('osm_id', $missing, true)) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->unique('osm_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty -- see the class docblock.
    }
};
