<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use App\Notifications\MarketplaceConnectionCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class FreightInterestAcceptanceController extends Controller
{
    public function accept(Request $request, int $id): RedirectResponse
    {
        $interest = FreightInterest::with('freightPost')->findOrFail($id);
        $user = $request->user();

        Gate::authorize('updateStatus', $interest);

        if ($interest->status !== FreightInterestStatus::Submitted->value && $interest->status !== FreightInterestStatus::Shortlisted->value) {
            return back()->withErrors(['status' => 'Interest cannot be accepted at this stage.']);
        }

        $post = $interest->freightPost;

        if ($post->status !== FreightPostStatus::Active->value) {
            return back()->withErrors(['post' => 'Freight post is not active.']);
        }

        $connection = DB::transaction(function () use ($interest, $post, $user) {
            // Idempotency: if connection exists, return existing without changing status
            $existing = MarketplaceConnection::where('freight_interest_id', $interest->id)->first();
            if ($existing) {
                return $existing;
            }

            $fromStatus = $interest->status;

            $interest->update(['status' => FreightInterestStatus::Selected->value]);
            $interest->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => FreightInterestStatus::Selected->value,
                'changed_by' => $user->id,
            ]);

            // Close the freight post
            $post->update(['status' => FreightPostStatus::Closed->value]);
            $post->moderationLogs()->create([
                'from_status' => FreightPostStatus::Active->value,
                'to_status' => FreightPostStatus::Closed->value,
                'reason' => 'Accepted by consignor',
                'moderator_id' => $user->id,
            ]);

            $connection = MarketplaceConnection::create([
                'public_id' => (string) Str::ulid(),
                'freight_post_id' => $post->id,
                'freight_interest_id' => $interest->id,
                'consignor_user_id' => $post->user_id,
                'transporter_user_id' => $interest->user_id,
            ]);

            $connection->auditLogs()->create([
                'action' => 'connection_created',
                'actor_id' => $user->id,
                'metadata' => ['interest_id' => $interest->id],
            ]);

            return $connection;
        });

        Notification::send([$post->user, $interest->user], new MarketplaceConnectionCreated($connection));

        return redirect()->route('connections.show', ['public_id' => $connection->public_id])
            ->with('success', 'Interest accepted. Connection created.');
    }
}
