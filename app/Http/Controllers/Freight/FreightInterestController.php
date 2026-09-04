<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightInterestRequest;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Notifications\FreightInterestSubmitted;
use App\Policies\FreightInterestPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class FreightInterestController extends Controller
{
    public function index(string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
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

        $policy = new FreightInterestPolicy;
        if (! $policy->create($user, $post)) {
            abort(403);
        }

        $interest = FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => FreightInterestStatus::Submitted->value,
            'message' => $request->input('message'),
            'contact_name' => $request->input('contact_name'),
            'contact_phone' => $request->input('contact_phone'),
            'contact_email' => $request->input('contact_email'),
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

        return back()->with('success', 'Interest expressed.');
    }
}
