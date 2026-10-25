<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Resources\FreightPostResource;
use App\Http\Resources\PostOwnerSummary;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\FreightPostRoute;
use App\Models\Location;
use App\Services\ContactRevealService;
use App\Services\MarketplaceEntitlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicFreightDiscoveryController extends Controller
{
    /**
     * Sort ids the query honors and the results header may announce.
     */
    private const SORT_OPTIONS = ['latest', 'distance_asc', 'distance_desc', 'pickup_asc'];

    public function __construct(
        protected ContactRevealService $reveals,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $cargo = $request->query('cargo');
        $sort = $request->query('sort', 'latest');

        // Echo only a sort that was actually applied. The results header
        // prints filters.sort as "Sorted by …", so an unrecognized value
        // must land on the default instead of being announced as if it ran.
        if (! is_string($sort) || ! in_array($sort, self::SORT_OPTIONS, true)) {
            $sort = 'latest';
        }

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

        // Apply sorting. The distance options order by the real route
        // snapshot: the menu promises kilometres, so the query must deliver
        // them — both used to fall through to the default pickup-date order.
        match ($sort) {
            'distance_asc' => $this->orderByDistance($query, 'asc'),
            'distance_desc' => $this->orderByDistance($query, 'desc'),
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
            //
            // Shape matters: the discover page reads freight.data /
            // freight.links / freight.total. A flat list falls through the
            // page's guard into an empty default and renders an empty
            // marketplace with live posts sitting in the database.
            'freight' => [
                // The list draws distances, never the polyline: geometry is
                // tens of kilobytes per route and stays on the surfaces that
                // actually render a map.
                'data' => array_map(function (array $row) {
                    Arr::forget($row, 'route_snapshot.geometry');

                    return $row;
                }, FreightPostResource::collection($posts->getCollection())->resolve()),
                'links' => $posts->linkCollection()->toArray(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ],
            'total' => $posts->total(),
            'links' => $posts->links(),
            'filters' => [
                'search' => $search,
                'origin' => $request->query('origin'),
                'destination' => $request->query('destination'),
                'origin_name' => $this->locationName($request->query('origin')),
                'destination_name' => $this->locationName($request->query('destination')),
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

        // Interest state is only collected when the loop is enabled. The board
        // reveal below is independent of it.
        $user = $request->user();
        $interestsEnabled = (bool) config('marketplace.interests_enabled');
        $hasInterest = false;
        $interestStatus = null;
        $interestId = null;
        $interestsCount = 0;

        if ($user && $interestsEnabled) {
            $interest = FreightInterest::where('freight_post_id', $post->id)->where('user_id', $user->id)->first();
            if ($interest) {
                $hasInterest = true;
                $interestStatus = $interest->status;
                // The id, not just the fact. The page needs it for the
                // flag-on withdraw and re-express actions.
                $interestId = (int) $interest->id;
            }

            if ($user->id === $post->user_id) {
                $interestsCount = FreightInterest::where('freight_post_id', $post->id)->count();
            }
        }

        // Whether this viewer may reveal the consignor's contact from this page,
        // and what is already revealed.
        //
        // The listing itself is the context: a signed-in non-owner may reveal
        // without expressing interest. canReveal() still returns true when the
        // viewer and the counterparty are the same person, so it is forced false
        // for the owner — the owner gets an Edit action instead.
        $canReveal = false;
        $revealedContacts = null;

        if ($user && $user->id !== $post->user_id && $post->user) {
            $canReveal = $this->reveals->canReveal($user, $post->user, $post);

            // Read-only. contactIfAlreadyRevealed() never records a reveal, so
            // loading this page cannot consume anybody's quota.
            $revealedContacts = $canReveal
                ? $this->reveals->contactIfAlreadyRevealed($user, $post->user, $post)
                : null;
        }

        if (! $interestsEnabled) {
            $interestsCount = null;
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
            // The owner is resolved flat rather than through the resource, which
            // nests it under a "data" key and leaves post.owner.id undefined —
            // the page compares that id to the viewer's to detect the owner, so
            // a nested owner read every owner as a visitor and offered them an
            // Express Interest button on their own load.
            'post' => array_merge(
                (new FreightPostResource($post))->resolve(),
                $post->user ? ['owner' => PostOwnerSummary::flat($post->user)] : [],
            ),
            'hasInterest' => $hasInterest,
            'interestStatus' => $interestStatus,
            'interestId' => $interestId,
            // Contact stays out of the payload until it has actually been
            // revealed. The page renders a button instead, and the button is what
            // goes through the metered, ledgered endpoint.
            'canReveal' => $canReveal,
            'revealedContacts' => $revealedContacts,
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
     * Order by the snapshot's distance, with posts the resolver never routed
     * last in both directions.
     *
     * The correlated subquery keeps this a single query (no join to clash with
     * the eager loads), and "(…) is null" sorts 0 — a real distance — before
     * 1 — no snapshot — on MySQL, Postgres, and SQLite alike.
     *
     * @param  Builder<FreightPost>  $query
     */
    private function orderByDistance(Builder $query, string $direction): void
    {
        $distance = FreightPostRoute::query()
            ->select('distance_meters')
            ->whereColumn('freight_post_routes.freight_post_id', 'freight_posts.id')
            ->toSql();

        $query->orderByRaw("({$distance}) is null asc")
            ->orderByRaw("({$distance}) {$direction}")
            ->orderByDesc('freight_posts.created_at');
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

    /**
     * Display name for an active location filter, so filter chips and inputs
     * can show "Djibouti" instead of a raw id. Non-numeric (OpenStreetMap)
     * values have no directory row and resolve to null; the frontend names
     * that case separately.
     */
    private function locationName(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $location = Location::query()->find($value);

        if (! $location) {
            return null;
        }

        return trim($location->name.', '.$location->region_name, ', ');
    }
}
