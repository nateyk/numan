<?php

namespace App\Services;

use App\Models\FreightPost;

class FreightCompletionService
{
    /**
     * Calculate completion score 0-100.
     * Route 20%, Cargo 20%, Truck 20%, Schedule 20%, Exact points 20%.
     */
    public function score(FreightPost $post): int
    {
        $score = 0;

        // Route locations 20% — both origin+dest 20, one 10
        $hasOrigin = ! empty($post->origin_location_id);
        $hasDest = ! empty($post->destination_location_id);
        if ($hasOrigin && $hasDest) {
            $score += 20;
        } elseif ($hasOrigin || $hasDest) {
            $score += 10;
        }

        // Cargo & Load 20% — cargo_type/item_name 10 + weight/tariff 10
        $hasCargo = ! empty($post->cargo_type_id) || ! empty($post->item_name);
        $hasLoad = ! empty($post->actual_weight) || ! empty($post->tariff_amount);
        if ($hasCargo && $hasLoad) {
            $score += 20;
        } elseif ($hasCargo || $hasLoad) {
            $score += 10;
        }

        // Truck 20% — truck type 10 + count 10
        if (! empty($post->required_truck_type_id)) {
            $score += 10;
        }
        if (! empty($post->truck_count) && (int) $post->truck_count > 0) {
            $score += 10;
        }

        // Schedule 20% — pickup_date 15 + schedule_type 5
        if (! empty($post->pickup_date)) {
            $score += 15;
        }
        if (! empty($post->schedule_type)) {
            $score += 5;
        }

        // Exact points 20% — each confirmed 10
        if (! empty($post->origin_point_confirmed_at)) {
            $score += 10;
        }
        if (! empty($post->destination_point_confirmed_at)) {
            $score += 10;
        }

        return min(100, $score);
    }

    public function update(FreightPost $post): FreightPost
    {
        $score = $this->score($post);
        $post->forceFill(['completion_score' => $score])->save();

        return $post->fresh();
    }
}
