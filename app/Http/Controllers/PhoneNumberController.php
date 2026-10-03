<?php

namespace App\Http\Controllers;

use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The inline "add your phone number" step.
 *
 * A user without a phone is never hard-blocked. The action they were trying to
 * do sends them back with requires_phone set, the layout renders this single
 * field, and saving it lets them retry. Nothing is created on their behalf
 * before they confirm.
 */
class PhoneNumberController extends Controller
{
    public function __construct(
        protected PhoneNumberService $phones,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['phone' => $this->phones->rules(required: true)],
            ['phone.phone' => PhoneNumberService::INVALID_MESSAGE],
        );

        $normalized = $this->phones->normalize($validated['phone']);

        if ($normalized === null) {
            return back()
                ->withErrors(['phone' => PhoneNumberService::INVALID_MESSAGE])
                ->withInput();
        }

        $user = $request->user();

        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            ['posts_freight' => false, 'provides_transport' => false],
        );

        $profile->forceFill(['phone' => $normalized])->save();

        return back()->with('success', 'Phone number saved. You can carry on.');
    }
}
