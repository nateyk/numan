<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFreightDiscoveryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = FreightPost::query()
            ->with(['user', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->active()
            ->when($request->query('origin'), function ($q, $origin) {
                $q->where('origin_location_id', $origin);
            })
            ->when($request->query('destination'), function ($q, $destination) {
                $q->where('destination_location_id', $destination);
            })
            ->when($request->query('cargo_type'), function ($q, $cargoType) {
                $q->where('cargo_type_id', $cargoType);
            })
            ->when($request->query('truck_type'), function ($q, $truckType) {
                $q->where('required_truck_type_id', $truckType);
            })
            ->when($request->query('pickup_from'), function ($q, $from) {
                $q->where('pickup_date', '>=', $from);
            })
            ->when($request->query('pickup_to'), function ($q, $to) {
                $q->where('pickup_date', '<=', $to);
            })
            ->orderByDesc('pickup_date')
            ->orderByDesc('created_at');

        $posts = $query->paginate(12)->withQueryString();

        return Inertia::render('discover/index', [
            'freight' => $posts,
            'filters' => $request->only(['origin', 'destination', 'cargo_type', 'truck_type', 'pickup_from', 'pickup_to']),
        ]);
    }
}
