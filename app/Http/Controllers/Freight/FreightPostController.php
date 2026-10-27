<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreightPostResource;
use App\Http\Resources\PostOwnerSummary;
use App\Models\FreightInterest;
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
        // A phone number on the profile is required to post. Carriers need a
        // way to reach a consignor, and contact details are only ever released
        // from the profile through the reveal action.
        //
        // Checked before the policy because readiness treats a missing phone as
        // not-ready. The user should get the inline field that fixes it rather
        // than a bare 403 from the gate.
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

    public function show(Request $request, string $ulid): Response|RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        $user = $request->user();

        // This URL is the owner's management page; anyone else belongs on the
        // public listing, which computes the viewer's interest and reveal
        // state. Rendering this page here for a non-owner left them on a URL
        // they cannot manage, with a dead "Manage Your Post" button.
        if (! $user || $user->id !== $post->user_id) {
            return redirect()->route('freight.public.show', $ulid);
        }

        // Eager-load the profile so display_name and verification_level resolve
        // in the same query rather than per-row on the card below.
        $post->load(['user.profile', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot']);

        return Inertia::render('freight/show', [
            // Resolved, like every other producer of this prop. Handing Inertia
            // the resource itself leaves the page with a post it cannot read a
            // route off, which is the same blank card this resource replaced.
            'post' => array_merge(
                (new FreightPostResource($post))->resolve(),
                $post->user ? ['owner' => PostOwnerSummary::flat($post->user)] : [],
            ),
            // The owner is nobody's bidder and cannot reveal their own number,
            // so these are fixed by construction rather than looked up.
            'hasInterest' => false,
            'interestStatus' => null,
            'interestId' => null,
            'canReveal' => false,
            'revealedContacts' => null,
            'interestsCount' => FreightInterest::where('freight_post_id', $post->id)->count(),
            // The owner may always see, and be told how to restore, their own
            // hidden listing — the public URL 404s it once a plan lapses.
            'isHiddenListing' => $post->isHidden(),
            'canRestoreListing' => true,
        ]);
    }

    public function destroy(Request $request, string $ulid): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();

        try {
            Gate::authorize('delete', $post);
        } catch (AuthorizationException) {
            // The owner clicking delete on a live post is a wrong-status
            // problem, not an intruder: explain it instead of a bare 403.
            if ($request->user()->id === $post->user_id) {
                return back()->withErrors(['freight' => 'Only drafts and finished posts can be deleted. Close or wait out a live post instead.']);
            }

            throw new AuthorizationException('You are not allowed to delete this freight post.');
        }

        $post->delete();

        return redirect()
            ->route('freight.index', $request->only(['status', 'search']))
            ->with('success', 'Freight post deleted.');
    }
}
