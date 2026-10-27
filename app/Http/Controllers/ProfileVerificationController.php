<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\VerificationStatus;
use App\Http\Requests\RequestProfileVerificationRequest;
use App\Models\Profile;
use App\Services\SavePublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Requests the single verification badge that covers both capabilities.
 */
class ProfileVerificationController extends Controller
{
    public function __construct(
        protected SavePublicProfile $profiles,
    ) {}

    public function store(RequestProfileVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profiles->save($user, []);
        Gate::authorize('update', $profile);

        $this->ensureIdentityIsComplete($profile);

        if (in_array($profile->verification_status, [
            VerificationStatus::Pending->value,
            VerificationStatus::Verified->value,
        ], true)) {
            abort(422, 'Your verification is already under review or approved.');
        }

        $profile->forceFill([
            'verification_status' => VerificationStatus::Pending->value,
            'verification_requested_at' => now(),
            'verification_reviewed_at' => null,
            'verification_note' => null,
        ])->save();

        return back()->with('success', 'Verification requested.');
    }

    /**
     * Verification reviews an identity, so the identity has to be present.
     */
    private function ensureIdentityIsComplete(Profile $profile): void
    {
        $missing = [];

        if (blank($profile->display_name)) {
            $missing[] = 'display name';
        }

        if (blank($profile->phone)) {
            $missing[] = 'phone number';
        }

        if ($profile->account_type === AccountType::Business->value) {
            if (blank($profile->legal_name)) {
                $missing[] = 'legal name';
            }

            if (blank($profile->legal_form)) {
                $missing[] = 'legal form';
            }
        }

        if ($missing !== []) {
            abort(422, 'Add your '.implode(', ', $missing).' before requesting verification.');
        }
    }
}
