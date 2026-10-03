<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A freight post as shown publicly.
 *
 * The owner is reduced to a display name and verification level. No email, no
 * phone. Hidden posts are excluded by the controller's visible() scope rather
 * than here.
 */
class FreightPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'ulid' => (string) $this->ulid,
            'status' => (string) $this->status,
            'cargo_description' => $this->cargo_description,
            'item_name' => $this->item_name,
            'actual_weight' => $this->actual_weight,
            'weight_unit' => $this->weight_unit,
            'tariff_weight' => $this->tariff_weight,
            'tariff_weight_unit' => $this->tariff_weight_unit,
            'tariff_amount' => $this->tariff_amount,
            'tariff_currency' => $this->tariff_currency,
            'insurance_required' => (bool) $this->insurance_required,
            'insurance_value' => $this->insurance_value,
            'depreciation_rate' => $this->depreciation_rate,
            'truck_count' => $this->truck_count,
            'schedule_type' => $this->schedule_type,
            'pickup_date' => $this->pickup_date?->toDateString(),
            'pickup_time' => $this->pickup_time,
            'pickup_date_flexibility_days' => $this->pickup_date_flexibility_days,
            'origin_site_note' => $this->origin_site_note,
            'destination_site_note' => $this->destination_site_note,
            'completion_score' => $this->completion_score,
            'created_at' => optional($this->created_at)->toIso8601String(),

            'origin' => $this->whenLoaded('originLocation', fn () => [
                'id' => $this->originLocation?->id,
                'name' => $this->originLocation?->name,
                'region_name' => $this->originLocation?->region_name,
                'latitude' => $this->originLocation?->latitude,
                'longitude' => $this->originLocation?->longitude,
            ]),
            'destination' => $this->whenLoaded('destinationLocation', fn () => [
                'id' => $this->destinationLocation?->id,
                'name' => $this->destinationLocation?->name,
                'region_name' => $this->destinationLocation?->region_name,
                'latitude' => $this->destinationLocation?->latitude,
                'longitude' => $this->destinationLocation?->longitude,
            ]),
            'cargo_type' => $this->whenLoaded('cargoType', fn () => [
                'id' => $this->cargoType?->id,
                'name' => $this->cargoType?->name,
                'slug' => $this->cargoType?->slug,
            ]),
            'truck_type' => $this->whenLoaded('truckType', fn () => [
                'id' => $this->truckType?->id,
                'name' => $this->truckType?->name,
                'slug' => $this->truckType?->slug,
            ]),

            // Display identity only.
            'owner' => new PostOwnerSummary($this->whenLoaded('user')),
        ];
    }
}
