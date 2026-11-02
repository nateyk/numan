<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Enums\VerificationStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * The one write path for a public profile.
 *
 * Both the management page and the first-post dialog save through here, so the
 * defaults a new profile gets cannot differ between the two entry points.
 */
class SavePublicProfile
{
    /**
     * Attributes the identity form is allowed to write.
     *
     * @var list<string>
     */
    private const ATTRIBUTES = [
        'account_type',
        'display_name',
        'phone',
        'whatsapp',
        'phone_numbers',
        'whatsapp_numbers',
        'email',
        'business_description',
        'primary_location_id',
        'legal_name',
        'legal_form',
        'legal_form_other',
        'phone_visible',
        'whatsapp_visible',
        'email_visible',
        'posts_freight',
        'provides_transport',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(User $user, array $attributes): Profile
    {
        $profile = $user->profile()->first();

        if (! $profile) {
            $profile = new Profile([
                'user_id' => $user->id,
                'display_name' => $attributes['display_name'] ?? $user->name,
                'email' => $attributes['email'] ?? $user->email,
                'posts_freight' => false,
                'provides_transport' => false,
                'verification_status' => VerificationStatus::Unverified->value,
                'completion_score' => 0,
            ]);
            $profile->save();
        }

        // A profile created before slugs existed has none. Repair it here so
        // every surface can link to the public page.
        if (blank($profile->slug)) {
            $profile->forceFill(['slug' => $profile->generateUniqueSlug()])->save();
        }

        foreach (['phone' => 'phone_numbers', 'whatsapp' => 'whatsapp_numbers'] as $primary => $numbers) {
            if (array_key_exists($numbers, $attributes)) {
                $attributes[$primary] = $attributes[$numbers][0] ?? null;
            } elseif (array_key_exists($primary, $attributes) && $profile->{$numbers} !== null) {
                $attributes[$numbers] = array_values(array_unique(array_filter([
                    $attributes[$primary],
                    ...array_slice($profile->{$numbers}, 1),
                ])));
            }
        }

        $profile->fill(Arr::only($attributes, self::ATTRIBUTES));

        // Turning transport on starts the listing lifecycle. Turning it off
        // deliberately leaves the status and the carrier data alone: approval
        // and verification survive a change of mind.
        if ((bool) $profile->provides_transport && $profile->status === null) {
            $profile->status = ProfileStatus::Draft->value;
        }

        $profile->save();

        return $profile->refresh();
    }
}
