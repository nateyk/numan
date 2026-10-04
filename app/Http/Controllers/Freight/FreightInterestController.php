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
                'user.profile',
                'user.transporterProfile.routes.originLocation',
                'user.transporterProfile.routes.destinationLocation',
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

        return Inertia::render('freight/interests', [
            'post' => (new FreightPostResource($post))->resolve(),
            'interests' => InterestResource::collection($interests)->resolve(),
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

        if (! $user->isProfileOnboarded()) {
            return back()->withErrors(['interest' => 'Please complete your profile onboarding before expressing interest.']);
        }

        $profile = $user->profile()->first();
        if (! $profile || ! $profile->provides_transport) {
            return back()->withErrors(['interest' => 'Your profile must be set to provide transport. Update your account type in onboarding.']);
        }

        $transporter = $user->transporterProfile()->first();
        if (! $transporter) {
            return back()->withErrors(['interest' => 'Please create a transporter profile first.']);
        }

        if ($transporter->status !== 'approved') {
            return back()->withErrors(['interest' => 'Your transporter profile is pending approval (status: '.$transporter->status.'). Please wait for admin approval.']);
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

        // A phone number on the profile is required. Contact details are
        // released from the profile through the reveal action, so an interest
        // cannot be expressed by someone we could never contact.
        //
        // Not a hard block: the layout renders one inline field and the user
        // retries. No interest row is created until they confirm.
        if (! $user->hasPhoneNumber()) {
            return back()
                ->with('requires_phone', true)
                ->with('requires_phone_reason', 'expressing interest')
                ->withErrors(['interest' => 'Add your phone number to continue. Carriers need a way to reach you.'])
                ->withInput();
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
