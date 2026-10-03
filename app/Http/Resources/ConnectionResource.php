<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A connection, without any contact details.
 *
 * Contact data is returned separately by the metered reveal action, never
 * inlined here.
 */
class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'public_id' => (string) $this->public_id,
            'status' => (string) $this->status,
            'freight_post_id' => (int) $this->freight_post_id,
            'freight_interest_id' => (int) $this->freight_interest_id,
            'created_at' => optional($this->created_at)->toIso8601String(),

            'freight_post' => $this->whenLoaded('freightPost', fn () => [
                'id' => (int) $this->freightPost->id,
                'ulid' => (string) $this->freightPost->ulid,
                'cargo_description' => $this->freightPost->cargo_description,
                'status' => (string) $this->freightPost->status,
                'origin' => $this->freightPost->originLocation?->name,
                'destination' => $this->freightPost->destinationLocation?->name,
            ]),

            // Display identity only, never the User model's own columns, which
            // include the email address.
            'consignor' => $this->whenLoaded('consignor', fn () => [
                'id' => (int) $this->consignor->id,
                'display_name' => $this->consignor->profile?->display_name ?? $this->consignor->name,
            ]),
            'transporter' => $this->whenLoaded('transporter', fn () => [
                'id' => (int) $this->transporter->id,
                'display_name' => $this->transporter->profile?->display_name ?? $this->transporter->name,
            ]),
            'outcome' => $this->whenLoaded('outcome'),
        ];
    }
}
