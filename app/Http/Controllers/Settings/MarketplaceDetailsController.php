<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MarketplaceDetailsRequest;
use App\Models\Location;
use App\Models\Profile;
use App\Services\MarketplaceReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The person's own view of their marketplace identity.
 *
 * /settings/profile edits the account: name, email, bio, locale. This edits the
 * person as a counterparty sees them: what they are called, how they can be
 * reached, and whether they agree to being reached that way.
 *
 * The split exists because those are genuinely different things. The account
 * record is the login. The profile record is what another user is agreeing to do
 * business with, and the rules around publishing it — the visibility flags — are
 * about the other person's consent, not the account holder's settings.
 */
class MarketplaceDetailsController extends Controller
{
    /**
     * The details themselves, plus everything needed to render the form.
     *
     * Created on demand. A user who has never touched the marketplace has no row,
     * and the page has to work for them without having sent them through
     * onboarding first.
     */
    public function edit(Request $request, MarketplaceReadiness $readiness): Response
    {
        $user = $request->user();
        $profile = Profile::firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => $user->name],
        );

        $state = $readiness->for($user);

        return Inertia::render('settings/marketplace-details', [
            'profile' => [
                'display_name' => $profile->display_name,
                'phone' => $profile->phone,
                'primary_location_id' => $profile->primary_location_id,
                'phone_visible' => (bool) $profile->phone_visible,
                'whatsapp_visible' => (bool) $profile->whatsapp_visible,
                'email_visible' => (bool) $profile->email_visible,
            ],
            'capabilities' => $state['capabilities'],
            'missingFields' => $state['missing'],
            'ready' => $state['ready'],
            'locations' => Location::orderBy('name')
                ->get(['id', 'name', 'type', 'region_name'])
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'label' => $location->region_name
                        ? $location->name.', '.$location->region_name
                        : $location->name,
                ]),
        ]);
    }

    public function update(MarketplaceDetailsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $profile = Profile::firstOrCreate(
            ['user_id' => $request->user()->id],
            [],
        );

        $profile->update([
            'display_name' => $data['display_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'primary_location_id' => $data['primary_location_id'] ?? null,

            // A checkbox that was never ticked arrives absent, not false. Treating
            // absence as consent would mean clearing the box and pressing save
            // silently republished a number the user had hidden.
            'phone_visible' => $request->boolean('phone_visible'),
            'whatsapp_visible' => $request->boolean('whatsapp_visible'),
            'email_visible' => $request->boolean('email_visible'),
        ]);

        return to_route('settings.marketplace-details.edit')
            ->with('success', 'Your details have been saved.');
    }
}
