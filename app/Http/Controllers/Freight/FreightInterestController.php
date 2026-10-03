<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightInterestRequest;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Notifications\FreightInterestSubmitted;
use App\Policies\FreightInterestPolicy;
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

        $interests = FreightInterest::where('freight_post_id', $post->id)
            ->with(['user'])
            ->latest()
            ->paginate(12);

        return Inertia::render('freight/interests', [
            'post' => $post,
            'interests' => $interests,
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

        $profile = $user->profile()->first();
        $transporter = $user->transporterProfile()->first();

        $interest = FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => FreightInterestStatus::Submitted->value,
            'message' => $request->input('message'),
            'contact_name' => $request->input('contact_name') ?: $user->name,
            'contact_phone' => $request->input('contact_phone') ?: ($profile?->phone ?? $transporter?->phone ?? ''),
            'contact_email' => $request->input('contact_email') ?: $user->email,
            'bank_account_name' => $request->input('bank_account_name'),
            'bank_name' => $request->input('bank_name'),
            'bank_account_number' => $request->input('bank_account_number'),
            'bank_iban' => $request->input('bank_iban'),
            'bank_swift' => $request->input('bank_swift'),
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
