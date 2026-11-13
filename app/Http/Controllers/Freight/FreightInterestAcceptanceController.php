<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use App\Models\FreightPost;
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
        $interest = FreightInterest::findOrFail($id);
        Gate::authorize('updateStatus', $interest);

        $result = DB::transaction(function () use ($id, $request): array {
            // Both competing interests share one freight row. Lock that row
            // first so only one acceptance can transition the listing to closed.
            $post = FreightPost::whereKey(
                FreightInterest::whereKey($id)->value('freight_post_id')
            )->lockForUpdate()->firstOrFail();

            $interest = FreightInterest::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('updateStatus', $interest);

            // Accept is idempotent for the winning interest, but a different
            // transporter must never receive a second connection.
            $existing = MarketplaceConnection::where('freight_post_id', $post->id)->first();
            if ($existing) {
                return (int) $existing->freight_interest_id === (int) $interest->id
                    ? ['connection' => $existing, 'created' => false]
                    : ['error' => 'Another transporter has already been selected for this freight.'];
            }

            if (! in_array($interest->status, [
                FreightInterestStatus::Submitted->value,
                FreightInterestStatus::Shortlisted->value,
            ], true)) {
                return ['error' => 'Interest cannot be accepted at this stage.'];
            }

            if ($post->status !== FreightPostStatus::Active->value) {
                return ['error' => 'Freight post is not active.'];
            }

            $fromStatus = $interest->status;
            $interest->update(['status' => FreightInterestStatus::Selected->value]);
            $interest->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => FreightInterestStatus::Selected->value,
                'changed_by' => $request->user()->id,
            ]);

            $post->update(['status' => FreightPostStatus::Closed->value]);
            $post->moderationLogs()->create([
                'from_status' => FreightPostStatus::Active->value,
                'to_status' => FreightPostStatus::Closed->value,
                'reason' => 'Accepted by consignor',
                'moderator_id' => $request->user()->id,
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
                'actor_id' => $request->user()->id,
                'metadata' => ['interest_id' => $interest->id],
            ]);

            return ['connection' => $connection, 'created' => true];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['status' => $result['error']]);
        }

        $connection = $result['connection'];

        if ($result['created']) {
            Notification::send(
                [$connection->consignor, $connection->transporter],
                new MarketplaceConnectionCreated($connection),
            );
        }

        // A connection never reveals contact details on its own.
        return redirect()->route('connections.show', ['public_id' => $connection->public_id])
            ->with('success', 'Interest accepted. You can now reveal contact details if you need them.');
    }
}
