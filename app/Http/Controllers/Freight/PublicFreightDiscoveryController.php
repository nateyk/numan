<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Resources\FreightPostResource;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Services\MarketplaceEntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicFreightDiscoveryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $cargo = $request->query('cargo');
        $sort = $request->query('sort', 'latest');

        $query = FreightPost::query()
            ->with(['user.profile', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->active()
            ->visible()
            ->when($request->query('origin'), function ($q, $origin) {
                $this->whereLocationId($q, 'origin_location_id', $origin);
            })
            ->when($request->query('destination'), function ($q, $destination) {
                $this->whereLocationId($q, 'destination_location_id', $destination);
            })
            ->when($request->query('cargo_type'), function ($q, $cargoType) {
                $q->where('cargo_type_id', $cargoType);
            })
            ->when($cargo, function ($q, $cargo) {
                if ($cargo !== 'all') {
                    $q->whereHas('cargoType', fn ($cq) => $cq->where('name', $cargo));
                }
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
            ->when($search, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('cargo_description', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('ulid', 'like', "%{$search}%");
                });
            });

        // Apply sorting
        match ($sort) {
            'distance_asc', 'distance_desc' => $query->orderByDesc('pickup_date')->orderByDesc('created_at'),
            'pickup_asc' => $query->orderBy('pickup_date', 'asc')->orderBy('created_at', 'asc'),
            default => $query->orderByDesc('pickup_date')->orderByDesc('created_at'),
        };

        $posts = $query->paginate(12)->withQueryString();

        // For current user, map freight_post_id => interest {id, status} to show on cards
        $userInterestMap = [];
        $user = $request->user();
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

        $marketplaceEntitlement = null;
        if ($user && $user->currentWorkspace) {
            $service = app(MarketplaceEntitlementService::class);
            $marketplaceEntitlement = [
                'can_post' => $service->canCreateFreightPost($user->currentWorkspace),
                'can_interest' => $service->canExpressInterest($user->currentWorkspace),
                'remaining_posts' => $service->remainingActivePosts($user->currentWorkspace),
                'plan' => $service->getPlanForWorkspace($user->currentWorkspace),
            ];
        }

        return Inertia::render('discover/index', [
            // Explicit columns only. The raw post includes the owner's User
            // model, whose email is not hidden by User::$hidden.
            'freight' => FreightPostResource::collection($posts->getCollection())->resolve(),
            'total' => $posts->total(),
            'links' => $posts->links(),
            'filters' => [
                'search' => $search,
                'origin' => $request->query('origin'),
                'destination' => $request->query('destination'),
                'cargo' => $cargo,
                'sort' => $sort,
                'cargo_type' => $request->query('cargo_type'),
                'truck_type' => $request->query('truck_type'),
                'pickup_from' => $request->query('pickup_from'),
                'pickup_to' => $request->query('pickup_to'),
            ],
            'userInterestMap' => (object) $userInterestMap,
            'marketplaceEntitlement' => $marketplaceEntitlement,
        ]);
    }

    public function show(Request $request, string $ulid): Response
    {
        // A post hidden because a paid plan lapsed 404s for everyone except the
        // owner and a superadmin. Existing interests and connections still
        // resolve, so only the public listing disappears.
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();

        if ($post->isHidden() && ! $this->mayManageHidden($request, $post)) {
            abort(404);
        }

        $post->load(['user.profile', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot']);

        Gate::authorize('view', $post);

        // Check if current user has already expressed interest
        $user = $request->user();
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

        $marketplaceEntitlement = null;
        if ($user && $user->currentWorkspace) {
            $service = app(MarketplaceEntitlementService::class);
            $marketplaceEntitlement = [
                'can_post' => $service->canCreateFreightPost($user->currentWorkspace),
                'can_interest' => $service->canExpressInterest($user->currentWorkspace),
                'plan' => $service->getPlanForWorkspace($user->currentWorkspace),
            ];
        }

        return Inertia::render('freight/show', [
            // Explicit columns only: the owner's email must not reach the page.
            'post' => (new FreightPostResource($post))->resolve(),
            'hasInterest' => $hasInterest,
            'interestStatus' => $interestStatus,
            'interestsCount' => $interestsCount,
            'marketplaceEntitlement' => $marketplaceEntitlement,
            // Lets the owner page render the "hidden, subscribe to restore"
            // badge and the link back to billing.
            'isHiddenListing' => $post->isHidden(),
            'canRestoreListing' => $this->mayManageHidden($request, $post),
        ]);
    }

    /**
     * Owners and superadmins keep full control of a hidden listing.
     */
    private function mayManageHidden(Request $request, FreightPost $post): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return (bool) $user->is_superadmin || $user->id === $post->user_id;
    }

    /**
     * Filter by a location id, treating anything non-numeric as matching nothing.
     *
     * A non-numeric value is an OpenStreetMap id, which has no locations row.
     * Passing it straight to where() coerces it to 0 on MySQL -- silently
     * matching rows with a 0 foreign key -- and raises a type error on Postgres.
     * Matching nothing is the honest answer, and the search box asks for local
     * rows only so this should not be reachable in the first place.
     *
     * @param  Builder<FreightPost>  $query
     */
    private function whereLocationId(Builder $query, string $column, mixed $value): void
    {
        if (is_numeric($value)) {
            $query->where($column, $value);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
