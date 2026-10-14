<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Changing what you do in the marketplace, after the fact.
 *
 * Every method on OnboardingController refuses once the user is onboarded, which
 * is correct — the wizard is a funnel and re-running it over a finished profile
 * would reset steps somebody already completed. But it left the capability choice
 * as a one-time decision, so somebody who registered to post a load and later
 * needed to carry one had no way to say so except by editing the database.
 *
 * This is that way. It never runs the wizard, never touches onboarding_step, and
 * never resets anything — it sets two booleans and creates an empty carrier
 * profile if one is now needed, so the readiness check has something to report.
 */
class CapabilityController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'posts_freight' => ['required', 'boolean'],
            'provides_transport' => ['required', 'boolean'],
        ]);

        if (! $validated['posts_freight'] && ! $validated['provides_transport']) {
            // Both booleans are submitted so the form can turn one off while
            // leaving the other on. Neither is a meaningful state: a user with no
            // capability cannot act, and an empty marketplace profile that reads
            // as "ready" is worse than one that admits it is inert.
            throw ValidationException::withMessages([
                'posts_freight' => __('Choose at least one — posting freight, carrying freight, or both.'),
            ]);
        }

        $user = $request->user();

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        $profile->forceFill([
            'posts_freight' => (bool) $validated['posts_freight'],
            'provides_transport' => (bool) $validated['provides_transport'],
        ])->save();

        // No carrier profile is created here.
        //
        // An earlier version of this created an empty one so the readiness check
        // would report missing fields rather than a missing record. It created a
        // dead end instead: TransporterProfileController::store refuses with
        // "Profile already exists. Use update." when a row is already there, so
        // picking up the capability made the very next action — filling in the
        // carrier form — fail with a message written for a developer.
        //
        // Readiness reporting `transporter_profile` is accurate and actionable on
        // its own: it says create a carrier profile, and creating one works.
        //
        // Turning a capability off likewise leaves the record alone. The user may
        // switch back tomorrow, and destroying their carrier profile — trucks,
        // routes and uploaded documents included — over a checkbox is
        // unforgivable.

        return back()->with('success', __('What you do here has been updated.'));
    }
}
