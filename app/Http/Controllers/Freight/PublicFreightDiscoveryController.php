<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // For current user, map freight_post_id => interest {id, status} to show on cards
        $userInterestMap = [];
        $user = auth()->user();
        if ($user) {
            $ids = $posts->getCollection()->pluck('id')->toArray();
            if (! empty($ids)) {
                $userInterests = FreightInterest::where('user_id', $user->id)
                    ->whereIn('freight_post_id', $ids)
                    ->get(['id', 'freight_post_id', 'status']);
                foreach ($userInterests as $interest) {
                    $userInterestMap[$interest->freight_post_id] = ['id' => $interest->id, 'status' => $interest->status];
                }
            }
        }

        return Inertia::render('discover/index', [
            'freight' => $posts,
            'filters' => $request->only(['origin', 'destination', 'cargo_type', 'truck_type', 'pickup_from', 'pickup_to']),
            'userInterestMap' => $userInterestMap,
        ]);
    }

    public function show(string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)
            ->with(['user', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->firstOrFail();

        Gate::authorize('view', $post);

        // Check if current user has already expressed interest
        $user = auth()->user();
        $hasInterest = false;
        $interestStatus = null;
        $interestsCount = 0;
        if ($user) {
            $interest = FreightInterest::where('freight_post_id', $post->id)->where('user_id', $user->id)->first();
            if ($interest) {
                $hasInterest = true;
                $interestStatus = $interest->status;
            }
            // For owner, provide interests count
            if ($user->id === $post->user_id) {
                $interestsCount = FreightInterest::where('freight_post_id', $post->id)->count();
            }
        }

        return Inertia::render('freight/show', [
            'post' => $post,
            'hasInterest' => $hasInterest,
            'interestStatus' => $interestStatus,
            'interestsCount' => $interestsCount,
        ]);
    }
}
