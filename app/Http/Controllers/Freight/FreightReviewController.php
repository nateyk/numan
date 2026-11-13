<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFreightPostRequest;
use App\Models\FreightPost;
use App\Models\Workspace;
use App\Services\FreightCompletionService;
use App\Services\MarketplaceEntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FreightReviewController extends Controller
{
    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        $post->load(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot']);

        $completion = app(FreightCompletionService::class);

        return Inertia::render('freight/review', [
            'post' => $post,
            'completionScore' => $completion->score($post),
            'requirements' => $completion->requirements($post),
        ]);
    }

    public function submit(SubmitFreightPostRequest $request, string $ulid, FreightCompletionService $completion): RedirectResponse
    {
        // A draft does not spend a live-post slot. Enforce the allowance at
        // the transition that spends it; serialize competing draft submissions
        // on their personal workspace row.
        return DB::transaction(function () use ($request, $ulid, $completion): RedirectResponse {
            $draft = FreightPost::where('ulid', $ulid)->firstOrFail();
            $workspace = $draft->workspace_id
                ? Workspace::whereKey($draft->workspace_id)->lockForUpdate()->first()
                : null;

            $post = FreightPost::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('submit', $post);

            if ($workspace && ! app(MarketplaceEntitlementService::class)->canCreateFreightPost($workspace)) {
                return back()->withErrors([
                    'freight' => 'Your live freight post limit has been reached. Close an active post, wait for it to expire, or upgrade before submitting this draft.',
                ]);
            }

            if (empty($post->origin_location_id) || empty($post->destination_location_id)) {
                return back()->withErrors(['origin_location_id' => 'Choose origin and destination.']);
            }

            // The wizard accepts either an item name or legacy cargo fields.
            if (empty($post->cargo_type_id) && empty($post->item_name) && empty($post->cargo_description)) {
                return back()->withErrors(['cargo_type_id' => 'Provide cargo details.']);
            }

            if (empty($post->required_truck_type_id)) {
                return back()->withErrors(['required_truck_type_id' => 'Select a truck type.']);
            }

            if (empty($post->pickup_date)) {
                return back()->withErrors(['pickup_date' => 'Select a pickup date.']);
            }

            if ($completion->score($post) < 60) {
                return back()->withErrors(['confirm' => 'Please complete the freight details before submitting.']);
            }

            $post->update(['status' => FreightPostStatus::Submitted->value]);
            $completion->update($post);

            $post->moderationLogs()->create([
                'from_status' => FreightPostStatus::Draft->value,
                'to_status' => FreightPostStatus::Submitted->value,
                'reason' => 'Submitted for review',
                'moderator_id' => $request->user()->id,
            ]);

            return redirect()->route('freight.index')->with('success', 'Freight submitted for review.');
        });
    }
}
