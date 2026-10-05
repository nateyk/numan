<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CargoType;
use App\Models\Location;
use App\Models\TruckType;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    public function cargoTypes(): JsonResponse
    {
        $cargoTypes = CargoType::active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'sort_order']);

        return response()->json(['cargo_types' => $cargoTypes]);
    }

    public function truckTypes(): JsonResponse
    {
        $truckTypes = TruckType::active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'icon', 'description', 'sort_order']);

        return response()->json(['truck_types' => $truckTypes]);
    }

    public function locations(): JsonResponse
    {
        $locations = Location::active()
            ->majorTowns()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'region_name', 'type', 'latitude', 'longitude', 'population']);

        return response()->json(['locations' => $locations]);
    }
}
