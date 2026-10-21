<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFreightPostRequest;
use App\Models\FreightPost;
use App\Services\FreightCompletionService;
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
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('submit', $post);

        // Validate completion and required fields
        $score = $completion->score($post);

        if (empty($post->origin_location_id) || empty($post->destination_location_id)) {
            return back()->withErrors(['origin_location_id' => 'Choose origin and destination.']);
        }

        // item_name is what the wizard collects; cargo_description is the
        // legacy column older rows still carry. Either satisfies the gate.
        if (empty($post->cargo_type_id) && empty($post->item_name) && empty($post->cargo_description)) {
            return back()->withErrors(['cargo_type_id' => 'Provide cargo details.']);
        }

        if (empty($post->required_truck_type_id)) {
            return back()->withErrors(['required_truck_type_id' => 'Select a truck type.']);
        }

        if (empty($post->pickup_date)) {
            return back()->withErrors(['pickup_date' => 'Select a pickup date.']);
        }

        if ($score < 60) {
            return back()->withErrors(['confirm' => 'Please complete the freight details before submitting.']);
        }

        $post->update(['status' => FreightPostStatus::Submitted->value]);
        $completion->update($post);

        // Log moderation
        $post->moderationLogs()->create([
            'from_status' => FreightPostStatus::Draft->value,
            'to_status' => FreightPostStatus::Submitted->value,
            'reason' => 'Submitted for review',
            'moderator_id' => $request->user()->id,
        ]);

        return redirect()->route('freight.index')->with('success', 'Freight submitted for review.');
    }
}
