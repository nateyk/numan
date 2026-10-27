<?php

namespace App\Services;

use App\Models\Profile;

/**
 * How complete a public profile is, as a single 0-100 number.
 *
 * Business fields carry most of the score because they are what a counterparty
 * reads before deciding whether to answer. The shape matches the retired carrier
 * completion service so the number does not jump when the profile is unified.
 */
class ProfileCompletionService
{
    public function calculate(Profile $profile): int
    {
        $score = 0;

        $businessFields = [
            ! empty($profile->display_name),
            ! empty($profile->phone),
            ! empty($profile->primary_location_id),
            ! empty($profile->business_description),
        ];
        $filled = count(array_filter($businessFields));
        $score += (int) round(($filled / count($businessFields)) * 40);

        $score += $profile->truckTypes()->exists() ? 30 : 0;
        $score += $profile->routes()->exists() ? 20 : 0;
        $score += $profile->documents()->exists() ? 10 : 0;

        return min(100, $score);
    }

    public function update(Profile $profile): Profile
    {
        $score = $this->calculate($profile);
        $profile->forceFill(['completion_score' => $score])->save();

        return $profile->fresh();
    }
}
