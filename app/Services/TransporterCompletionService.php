<?php

namespace App\Services;

use App\Models\TransporterProfile;

class TransporterCompletionService
{
    /**
     * Calculate completion score 0-100.
     * Business info 40%, Fleet 30%, Routes 20%, Docs 10%.
     */
    public function calculate(TransporterProfile $profile): int
    {
        $score = 0;

        // Business info 40% — display_name, phone, primary_location, description
        $businessFields = [
            ! empty($profile->display_name),
            ! empty($profile->phone),
            ! empty($profile->primary_location_id),
            ! empty($profile->business_description),
        ];
        $filled = count(array_filter($businessFields));
        $score += (int) round(($filled / count($businessFields)) * 40);

        // Fleet 30% — at least one truck type
        $score += $profile->truckTypes()->exists() ? 30 : 0;

        // Routes 20% — at least one operating route
        $score += $profile->routes()->exists() ? 20 : 0;

        // Docs 10% — at least one document
        $score += $profile->documents()->exists() ? 10 : 0;

        return min(100, $score);
    }

    public function update(TransporterProfile $profile): TransporterProfile
    {
        $score = $this->calculate($profile);
        $profile->forceFill(['completion_score' => $score])->save();

        return $profile->fresh();
    }
}
