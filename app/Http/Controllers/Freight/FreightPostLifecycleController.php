<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FreightPostLifecycleController extends Controller
{
    public function close(Request $request, string $ulid): RedirectResponse
    {
        // Serialize against competing transporter acceptance, which locks the
        // same freight post before closing the listing.
        DB::transaction(function () use ($request, $ulid): void {
            $post = FreightPost::where('ulid', $ulid)->lockForUpdate()->firstOrFail();
            Gate::authorize('close', $post);

            if ($post->status === FreightPostStatus::Closed->value) {
                return;
            }

            $from = $post->status;
            $post->update([
                'status' => FreightPostStatus::Closed->value,
                'closed_at' => now(),
            ]);
            $post->moderationLogs()->create([
                'from_status' => $from,
                'to_status' => FreightPostStatus::Closed->value,
                'reason' => 'Listing closed by consignor',
                'moderator_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Freight listing closed. It is no longer available to transporters.');
    }

    public function arrive(Request $request, string $ulid): RedirectResponse
    {
        DB::transaction(function () use ($request, $ulid): void {
            $post = FreightPost::where('ulid', $ulid)->lockForUpdate()->firstOrFail();
            Gate::authorize('markArrived', $post);

            if ($post->arrived_at !== null) {
                return;
            }

            // Closed means the listing is no longer seeking a transporter;
            // arrival confirms that the cargo was delivered afterwards.
            $post->update(['arrived_at' => now()]);
            $post->moderationLogs()->create([
                'from_status' => FreightPostStatus::Closed->value,
                'to_status' => FreightPostStatus::Closed->value,
                'reason' => 'Cargo arrival confirmed by consignor',
                'moderator_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Cargo marked as arrived.');
    }
}
