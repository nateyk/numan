<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProfileSetupRequest;
use App\Services\PhoneNumberService;
use App\Services\ProfileCompletionService;
use App\Services\SavePublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The first-post dialog's save endpoint.
 *
 * Posting is what the person clicked, so `posts_freight` is set here. Carrying
 * is optional and may be switched on at the same time.
 */
class ProfileSetupController extends Controller
{
    public function __construct(
        protected SavePublicProfile $profiles,
        protected ProfileCompletionService $completion,
    ) {}

    public function store(CompleteProfileSetupRequest $request, PhoneNumberService $phones): RedirectResponse
    {
        $data = $request->validated();

        $phone = $phones->normalize($data['phone']);

        if ($phone === null) {
            return back()
                ->withErrors(['phone' => PhoneNumberService::INVALID_MESSAGE])
                ->withInput();
        }

        $profile = $this->profiles->save($request->user(), []);
        Gate::authorize('update', $profile);

        $profile = $this->profiles->save($request->user(), [
            'display_name' => $data['display_name'],
            'account_type' => $data['account_type'],
            'phone' => $phone,
            'posts_freight' => true,
            'provides_transport' => (bool) $data['provides_transport'],
            'primary_location_id' => $data['primary_location_id'] ?? null,
        ]);

        $this->completion->update($profile);

        return back()->with('success', 'Profile ready. You can post freight now.');
    }
}
