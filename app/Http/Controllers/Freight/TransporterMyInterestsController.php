<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use App\Notifications\FreightInterestStatusChanged;
use App\Policies\FreightInterestPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class TransporterMyInterestsController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->query('sort', 'latest');

        $query = FreightInterest::where('user_id', $request->user()->id)
            ->with(['freightPost.user', 'freightPost.originLocation', 'freightPost.destinationLocation', 'freightPost.cargoType', 'freightPost.truckType']);

        switch ($sort) {
            case 'status':
                $query->orderBy('status');
                break;
            case 'oldest':
                $query->oldest();
                break;
            default:
                $query->latest();
        }

        $interests = $query->paginate(12)->withQueryString();

        $counts = [
            'all' => FreightInterest::where('user_id', $request->user()->id)->count(),
            'submitted' => FreightInterest::where('user_id', $request->user()->id)->where('status', 'submitted')->count(),
            'shortlisted' => FreightInterest::where('user_id', $request->user()->id)->where('status', 'shortlisted')->count(),
            'selected' => FreightInterest::where('user_id', $request->user()->id)->where('status', 'selected')->count(),
        ];

        return Inertia::render('freight/my-interests', [
            'interests' => $interests,
            'sort' => $sort,
            'counts' => $counts,
        ]);
    }

    public function withdraw(Request $request, int $id): RedirectResponse
    {
        $interest = FreightInterest::findOrFail($id);
        $user = $request->user();

        $policy = new FreightInterestPolicy;
        if (! $policy->withdraw($user, $interest)) {
            abort(403);
        }

        $from = FreightInterestStatus::tryFrom($interest->status);
        $to = FreightInterestStatus::Withdrawn;

        if (! $from || ! $from->canTransitionTo($to)) {
            return back()->withErrors(['status' => 'Cannot withdraw at this stage.']);
        }

        $oldStatus = $interest->status;
        $interest->update(['status' => $to->value]);
        $interest->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $user->id,
        ]);

        $interest->load('freightPost');
        Notification::send($interest->freightPost->user, new FreightInterestStatusChanged($interest, $interest->freightPost, $oldStatus, $to->value));

        return back()->with('success', 'Interest withdrawn.');
    }
}
