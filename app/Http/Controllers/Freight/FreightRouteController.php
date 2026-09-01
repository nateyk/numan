<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightRouteRequest;
use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\TruckType;
use App\Services\FreightCompletionService;
use App\Services\RoutePreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FreightRouteController extends Controller
{
    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('update', $post);

        return Inertia::render('freight/route', [
            'post' => $post->load(['originLocation', 'destinationLocation', 'routeSnapshot']),
            'locations' => Location::active()->orderBy('name')->limit(50)->get(['id', 'name', 'region_name', 'slug']),
            'routePreview' => $post->routeSnapshot,
        ]);
    }

    public function update(StoreFreightRouteRequest $request, string $ulid, FreightCompletionService $completion, RoutePreviewService $preview): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('update', $post);

        $data = $request->validated();

        $post->fill($data);
        $post->save();

        $completion->update($post);

        // Persist route snapshot if coords present
        if (! empty($data['origin_exact_lat']) && ! empty($data['origin_exact_lng']) && ! empty($data['destination_exact_lat']) && ! empty($data['destination_exact_lng'])) {
            $previewData = $preview->preview(
                (float) $data['origin_exact_lat'],
                (float) $data['origin_exact_lng'],
                (float) $data['destination_exact_lat'],
                (float) $data['destination_exact_lng']
            );

            if ($previewData) {
                $post->routeSnapshot()->updateOrCreate(
                    ['freight_post_id' => $post->id],
                    [
                        'distance_meters' => $previewData['distance_meters'],
                        'duration_seconds' => $previewData['duration_seconds'],
                        'geometry' => $previewData['geometry'],
                        'routing_provider' => $previewData['provider'],
                    ]
                );
            }
        }

        return redirect()->route('freight.cargo.show', ['ulid' => $post->ulid]);
    }
}
