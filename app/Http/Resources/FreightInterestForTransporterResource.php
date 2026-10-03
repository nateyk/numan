<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the viewer's own interests, with the freight post it refers to.
 *
 * The interest row itself carries no contact or bank fields, and the post's
 * owner is reduced to a display name. Contact details for the consignor come
 * from the reveal action.
 */
class FreightInterestForTransporterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'message' => $this->message,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'freight_post_id' => (int) $this->freight_post_id,

            'freight_post' => $this->whenLoaded('freightPost', fn () => [
                'id' => (int) $this->freightPost->id,
                'ulid' => (string) $this->freightPost->ulid,
                'status' => (string) $this->freightPost->status,
                'cargo_description' => $this->freightPost->cargo_description,
                'pickup_date' => $this->freightPost->pickup_date?->toDateString(),
                'origin' => $this->freightPost->originLocation?->name,
                'destination' => $this->freightPost->destinationLocation?->name,

                // Display identity only, no email.
                'owner' => $this->freightPost->user ? [
                    'id' => (int) $this->freightPost->user->id,
                    'display_name' => $this->freightPost->user->profile?->display_name
                        ?? $this->freightPost->user->name,
                ] : null,
            ]),
        ];
    }
}
