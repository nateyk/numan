<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransporterMyInterestsController extends Controller
{
    public function index(Request $request): Response
    {
        $interests = FreightInterest::where('user_id', $request->user()->id)
            ->with(['freightPost.originLocation', 'freightPost.destinationLocation'])
            ->latest()
            ->paginate(12);

        return Inertia::render('freight/my-interests', [
            'interests' => $interests,
        ]);
    }

    public function withdraw(Request $request, int $id): RedirectResponse
    {
        $interest = FreightInterest::findOrFail($id);
        $user = $request->user();

        $policy = new \App\Policies\FreightInterestPolicy();
        if (! $policy->withdraw($user, $interest)) {
            abort(403);
        }

        $from = FreightInterestStatus::tryFrom($interest->status);
        $to = FreightInterestStatus::Withdrawn;

        if (! $from || ! $from->canTransitionTo($to)) {
            return back()->withErrors(['status' => 'Cannot withdraw at this stage.']);
        }

        $interest->update(['status' => $to->value]);
        $interest->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'changed_by' => $user->id,
        ]);

        return back()->with('success', 'Interest withdrawn.');
    }
}
