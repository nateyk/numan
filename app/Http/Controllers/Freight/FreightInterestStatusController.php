<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use App\Notifications\FreightInterestStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class FreightInterestStatusController extends Controller
{
    public function shortlist(Request $request, int $id): RedirectResponse
    {
        $interest = FreightInterest::with('freightPost')->findOrFail($id);
        $user = $request->user();

        if ($user->id !== $interest->freightPost->user_id) {
            abort(403);
        }

        $from = FreightInterestStatus::tryFrom($interest->status);
        $to = FreightInterestStatus::Shortlisted;

        if (! $from || ! $from->canTransitionTo($to)) {
            return back()->withErrors(['status' => 'Invalid transition.']);
        }

        $oldStatus = $interest->status;
        $interest->update(['status' => $to->value]);
        $interest->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $user->id,
        ]);

        Notification::send($interest->user, new FreightInterestStatusChanged(
            $interest, $interest->freightPost, $oldStatus, $to->value
        ));

        return back()->with('success', 'Interest shortlisted.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $interest = FreightInterest::with('freightPost')->findOrFail($id);
        $user = $request->user();

        if ($user->id !== $interest->freightPost->user_id) {
            abort(403);
        }

        $from = FreightInterestStatus::tryFrom($interest->status);
        $to = FreightInterestStatus::Rejected;

        if (! $from || ! $from->canTransitionTo($to)) {
            return back()->withErrors(['status' => 'Invalid transition.']);
        }

        $oldStatus = $interest->status;
        $interest->update(['status' => $to->value]);
        $interest->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $user->id,
        ]);

        Notification::send($interest->user, new FreightInterestStatusChanged(
            $interest, $interest->freightPost, $oldStatus, $to->value
        ));

        return back()->with('success', 'Interest rejected.');
    }
}
