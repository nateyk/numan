<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreightInterestForTransporterResource;
use App\Models\FreightInterest;
use App\Notifications\FreightInterestStatusChanged;
use App\Policies\FreightInterestPolicy;
use App\Services\ContactRevealService;
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
            // Explicit relations: freightPost.user.profile rather than
            // freightPost.user, so the consignor's email is never loaded.
            ->with(['freightPost.user.profile', 'freightPost.originLocation', 'freightPost.destinationLocation', 'freightPost.cargoType', 'freightPost.truckType']);

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

        // Explicit columns only. The interest row must not carry the consignor's
        // contact or bank fields to the transporter's browser, and the reveal
        // endpoint is the only place contact data is returned.
        $revealService = app(ContactRevealService::class);
        $viewer = $request->user();

        $canReveal = [];
        $revealedContacts = [];

        foreach ($interests->getCollection() as $interest) {
            $consignor = $interest->freightPost?->user;
            $canReveal[$interest->id] = $consignor !== null
                && $revealService->canReveal($viewer, $consignor);

            if ($consignor === null) {
                continue;
            }

            $contact = $revealService->contactIfAlreadyRevealed($viewer, $consignor, $interest);

            if ($contact !== null) {
                $revealedContacts[$interest->id] = $contact;
            }
        }

        return Inertia::render('freight/my-interests', [
            'interests' => FreightInterestForTransporterResource::collection($interests->getCollection())->resolve(),
            'total' => $interests->total(),
            'links' => $interests->links(),
            'canReveal' => $canReveal,
            'revealedContacts' => $revealedContacts,
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
