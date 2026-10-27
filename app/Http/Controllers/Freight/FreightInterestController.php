<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightInterestRequest;
use App\Http\Resources\FreightPostResource;
use App\Http\Resources\InterestResource;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Notifications\FreightInterestSubmitted;
use App\Policies\FreightInterestPolicy;
use App\Services\ContactRevealService;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class FreightInterestController extends Controller
{
    public function index(string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->with(['originLocation', 'destinationLocation'])->firstOrFail();
        Gate::authorize('view', $post);

        // Only owner or superadmin can view interests for this freight
        if (auth()->user()->id !== $post->user_id && ! auth()->user()->is_superadmin) {
            abort(403);
        }

        // Explicit columns only. The interest row carries contact and bank
        // fields that must never reach the consignor's browser.
        $interests = FreightInterest::where('freight_post_id', $post->id)
            ->with([
                'user.profile.routes.originLocation',
                'user.profile.routes.destinationLocation',
            ])
            ->latest()
            ->paginate(12);

        $revealService = app(ContactRevealService::class);
        $viewer = auth()->user();

        // Tell the page which rows offer a reveal button, and include the
        // contact only where it has already been revealed. Reading it never
        // records a reveal, so loading this page cannot consume quota.
        $canReveal = [];
        $revealedContacts = [];

        foreach ($interests as $interest) {
            $canReveal[$interest->id] = $revealService->canReveal($viewer, $interest->user);

            $contact = $revealService->contactIfAlreadyRevealed($viewer, $interest->user, $interest);

            if ($contact !== null) {
                $revealedContacts[$interest->id] = $contact;
            }
        }

        // Tab counts cover every interest on the post, not just this page.
        // The page used to count its own slice and label it "N total".
        $statusCounts = FreightInterest::query()
            ->where('freight_post_id', $post->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('freight/interests', [
            'post' => (new FreightPostResource($post))->resolve(),
            'interests' => InterestResource::collection($interests)->resolve(),
            'total' => $interests->total(),
            'links' => $interests->linkCollection()->toArray(),
            'counts' => [
                'all' => (int) $statusCounts->sum(),
                'submitted' => (int) $statusCounts->get(FreightInterestStatus::Submitted->value, 0),
                'shortlisted' => (int) $statusCounts->get(FreightInterestStatus::Shortlisted->value, 0),
                'selected' => (int) $statusCounts->get(FreightInterestStatus::Selected->value, 0),
            ],
            'canReveal' => $canReveal,
            'revealedContacts' => $revealedContacts,
        ]);
    }

    public function store(StoreFreightInterestRequest $request, string $ulid): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        $user = $request->user();

        // Detailed checks to return user-friendly errors instead of generic 403
        if ($user->id === $post->user_id) {
            return back()->withErrors(['interest' => 'You cannot express interest on your own freight.']);
        }

        // The phone step runs before the readiness gate. A user missing only a
        // phone should get the inline field and retry, not a generic list of
        // missing profile fields that does not say how to fix any of them.
        if (! $user->hasPhoneNumber()) {
            return back()
                ->with('requires_phone', true)
                ->with('requires_phone_reason', 'expressing interest')
                ->withErrors(['interest' => 'Add your phone number to continue. Carriers need a way to reach you.'])
                ->withInput();
        }

        $readiness = app(MarketplaceReadiness::class)->for($user);

        if (! $readiness['capabilities']['provides_transport']) {
            return back()->withErrors(['interest' => 'Your public profile must be set to provide transport before expressing interest.']);
        }

        if (! $readiness['ready']['provides_transport']) {
            return back()->withErrors([
                'interest' => 'Complete your public profile to express interest. Missing: '
                    .implode(', ', $readiness['missing']['provides_transport']).'.',
            ]);
        }

        if ($post->status !== FreightPostStatus::Active->value) {
            return back()->withErrors(['interest' => 'This freight is not active (status: '.$post->status.'). You can only express interest on active freights.']);
        }

        // A listing hidden by a lapsed plan no longer accepts new interests.
        // Interests and connections that already exist keep working.
        if ($post->isHidden()) {
            return back()->withErrors(['interest' => 'This freight is no longer listed publicly.']);
        }

        $exists = FreightInterest::where('freight_post_id', $post->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [FreightInterestStatus::Rejected->value, FreightInterestStatus::Withdrawn->value])
            ->exists();

        if ($exists) {
            return back()->withErrors(['interest' => 'You have already expressed interest on this freight.']);
        }

        $workspace = $user->currentWorkspace;
        if ($workspace) {
            $entitlement = app(MarketplaceEntitlementService::class);
            if (! $entitlement->canExpressInterest($workspace)) {
                return back()->withErrors(['interest' => 'Monthly interest limit reached for your workspace. Please upgrade your plan.']);
            }
        }

        $policy = new FreightInterestPolicy;
        if (! $policy->create($user, $post)) {
            return back()->withErrors(['interest' => 'You are not authorized to express interest on this freight.']);
        }

        $interest = FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => FreightInterestStatus::Submitted->value,
            'message' => $request->input('message'),
            // No per-interest contact or bank fields. The columns remain in the
            // database but are never written or shown. NumanFreight handles no
            // payments, so bank details have no place on an interest.
        ]);

        $interest->statusHistories()->create([
            'from_status' => null,
            'to_status' => FreightInterestStatus::Submitted->value,
            'changed_by' => $user->id,
        ]);

        // Notify consignor
        Notification::send($post->user, new FreightInterestSubmitted($interest, $post->load('user')));

        return redirect()->route('freight.public.show', ['ulid' => $post->ulid])->with('success', 'Interest expressed.');
    }
}
