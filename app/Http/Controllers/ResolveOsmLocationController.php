<?php

namespace App\Http\Controllers;

use App\Services\OsmLocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turns a picked OpenStreetMap search result into a real locations row.
 *
 * Search returns OSM results with an "osm_" prefixed id because they have no
 * database id yet. Anything that stores a location_id -- the freight route step,
 * the public profile -- needs a real row, so the pick is posted here and
 * resolved. Nominatim returns the same feature for the same query forever, so the
 * resolver's osm_id key is what stops each search inserting another copy.
 */
class ResolveOsmLocationController extends Controller
{
    public function __invoke(Request $request, OsmLocationResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'osm_type' => ['required', 'string', 'max:32'],
            'osm_id' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:32'],
            'region_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_major_town' => ['nullable', 'boolean'],
        ]);

        $location = $resolver->resolve($validated);

        if ($location === null) {
            return response()->json(['message' => 'That location could not be saved.'], 422);
        }

        return response()->json([
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'region_name' => $location->region_name,
                'slug' => $location->slug,
                'type' => $location->type,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'is_major_town' => $location->is_major_town,
                'source' => $location->source,
            ],
        ]);
    }
}
