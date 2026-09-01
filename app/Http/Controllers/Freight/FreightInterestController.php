<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightInterestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightInterestRequest;
use App\Models\FreightPost;
use App\Models\FreightInterest;
use App\Policies\FreightInterestPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FreightInterestController extends Controller
{
    public function store(StoreFreightInterestRequest $request, string $ulid): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        $user = $request->user();

        $policy = new FreightInterestPolicy();
        if (! $policy->create($user, $post)) {
            abort(403);
        }

        $interest = FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => FreightInterestStatus::Submitted->value,
            'message' => $request->input('message'),
        ]);

        $interest->statusHistories()->create([
            'from_status' => null,
            'to_status' => FreightInterestStatus::Submitted->value,
            'changed_by' => $user->id,
        ]);

        return back()->with('success', 'Interest expressed.');
    }
}
