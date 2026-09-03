<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['locations' => []]);
        }

        $results = Location::query()
            ->active()
            ->search($term)
            ->orderByDesc('is_major_town')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'region_name', 'slug', 'type', 'latitude', 'longitude', 'population', 'elevation', 'wereda', 'zone', 'is_major_town']);

        return response()->json(['locations' => $results]);
    }
}
