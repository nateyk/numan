<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use App\Services\MarketplaceEntitlementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FreightPostController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FreightPost::class);

        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = FreightPost::query()
            ->ownedBy($request->user())
            ->with(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->withCount('interests')
            ->latest();

        if ($status !== 'all' && in_array($status, FreightPostStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('cargo_description', 'like', "%{$search}%")
                    ->orWhere('ulid', 'like', "%{$search}%")
                    ->orWhereHas('originLocation', fn ($oq) => $oq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationLocation', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $posts = $query->paginate(12)->withQueryString();

        $base = FreightPost::ownedBy($request->user());
        $counts = [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', FreightPostStatus::Draft->value)->count(),
            'submitted' => (clone $base)->where('status', FreightPostStatus::Submitted->value)->count(),
            'under_review' => (clone $base)->where('status', FreightPostStatus::UnderReview->value)->count(),
            'active' => (clone $base)->where('status', FreightPostStatus::Active->value)->count(),
            'closed' => (clone $base)->where('status', FreightPostStatus::Closed->value)->count(),
            'expired' => (clone $base)->where('status', FreightPostStatus::Expired->value)->count(),
            'rejected' => (clone $base)->where('status', FreightPostStatus::Rejected->value)->count(),
        ];

        return Inertia::render('freight/index', [
            'posts' => $posts,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => $counts,
            // Hidden posts stay listed here for the owner; only public
            // visibility is withdrawn.
            'hiddenCount' => (clone $base)->hidden()->count(),
            // Free-plan quota, so the UI can explain the limit before the
            // user fires a create request the policy would reject.
            'canPost' => Gate::allows('create', FreightPost::class),
            'remainingLivePosts' => app(MarketplaceEntitlementService::class)->remainingLivePosts($request->user()->currentWorkspace),
            'livePostLimit' => app(MarketplaceEntitlementService::class)->getLimitValue($request->user()->currentWorkspace, 'active_freight_posts'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            Gate::authorize('create', FreightPost::class);
        } catch (AuthorizationException) {
            // A workspace over its Free live-post limit gets a plain 403 from
            // the policy. Explain it instead: send the user back with the
            // numbers and a path to upgrade, rather than an error page.
            $workspace = $request->user()->currentWorkspace;
            $entitlement = app(MarketplaceEntitlementService::class);

            if ($workspace && ! $entitlement->canCreateFreightPost($workspace)) {
                return back()
                    ->with('limit_reached', [
                        'live' => $entitlement->livePostCount($workspace),
                        'limit' => $entitlement->getLimitValue($workspace, 'active_freight_posts'),
                        'plans_url' => route('billing.plans'),
                    ])
                    ->withErrors(['freight' => 'You have reached the Free plan limit for live freight posts. Close or wait for an existing post to expire, or upgrade your plan to post more.']);
            }

            throw new AuthorizationException('You are not allowed to post freight.');
        }

        // A phone number on the profile is required to post. Carriers need a
        // way to reach a consignor, and contact details are only ever released
        // from the profile through the reveal action.
        //
        // The user is not blocked: they are sent back with requires_phone set,
        // the layout shows one inline field, and saving it lets them retry. No
        // draft is created until they confirm.
        if (! $request->user()->hasPhoneNumber()) {
            return back()
                ->with('requires_phone', true)
                ->with('requires_phone_reason', 'posting freight')
                ->withErrors(['freight' => 'Add your phone number to continue. Carriers need a way to reach you.'])
                ->withInput();
        }

        $workspace = $request->user()->currentWorkspace;

        $post = FreightPost::create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $request->user()->id,
            'workspace_id' => $workspace?->id,
            'status' => FreightPostStatus::Draft->value,
            'completion_score' => 0,
        ]);

        return redirect()->route('freight.route.show', ['ulid' => $post->ulid]);
    }

    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        $post->load(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot']);

        return Inertia::render('freight/show', [
            'post' => $post,
        ]);
    }
}
