<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An interest, as seen by the counterparty.
 *
 * Deliberately excludes contact_phone, contact_email and every bank_* field.
 * Those are not part of the interest any more: contact details are revealed
 * from the person's profile through the metered reveal action, and NumanFreight
 * handles no payments, so bank details have no place on an interest row.
 */
class InterestResource extends JsonResource
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

            // Display identity only. No email, no phone.
            'transporter' => $this->whenLoaded('user', fn () => [
                'id' => (int) $this->user->id,
                'display_name' => $this->user->profile?->display_name ?? $this->user->name,
                'is_verified' => (bool) $this->user->profile?->verification_level,
                'routes' => $this->user->transporterProfile?->routes->map(fn ($route) => [
                    'id' => (int) $route->id,
                    'origin' => $route->originLocation?->name,
                    'destination' => $route->destinationLocation?->name,
                ])->values() ?? [],
            ]),
        ];
    }
}
