<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A transporter profile as seen by other users.
 *
 * Never includes phone, whatsapp, email or legal_name. Those are released only
 * through the metered reveal action. Directory and public profile pages both
 * use this.
 */
class TransporterPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'slug' => (string) $this->slug,
            'display_name' => (string) $this->display_name,
            'status' => (string) $this->status,
            'verification_level' => $this->verification_level,
            'is_verified' => (bool) $this->verification_level,
            'rating' => $this->rating ?? null,
            'total_routes' => (int) ($this->total_routes ?? 0),
            'business_description' => $this->business_description,
            'is_company' => (bool) $this->is_company,
            'completion_score' => (int) ($this->completion_score ?? 0),

            'primary_location' => $this->whenLoaded('primaryLocation', fn () => [
                'id' => $this->primaryLocation?->id,
                'name' => $this->primaryLocation?->name,
                'region_name' => $this->primaryLocation?->region_name,
            ]),

            'truck_types' => $this->whenLoaded('truckTypes', fn () => $this->truckTypes->map(fn ($type) => [
                'id' => (int) $type->id,
                'name' => (string) $type->name,
                'slug' => (string) $type->slug,
            ])->values()),

            'routes' => $this->whenLoaded('routes', fn () => $this->routes->map(fn ($route) => [
                'id' => (int) $route->id,
                'origin' => $route->originLocation?->name,
                'destination' => $route->destinationLocation?->name,
            ])->values()),

            // Document names only. The file URL is deliberately excluded: a
            // public profile page must not hand out a direct download link to
            // somebody's licence or ID.
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => (int) $document->id,
                'name' => $document->name ?? $document->type,
                'type' => $document->type,
                'uploaded_at' => optional($document->created_at)->toIso8601String(),
            ])->values()),
        ];
    }
}
