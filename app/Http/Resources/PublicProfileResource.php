<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public view of a marketplace profile.
 *
 * A profile is the only public identity a user has: it can post freight,
 * provide transport, or both. This resource is shared by the public profile
 * page and the carrier directory, so it carries exactly what both may show and
 * nothing else.
 *
 * Never add phone, WhatsApp, email, legal name, or document identifiers here.
 * Those are released only through the metered reveal action, and a document is
 * downloadable only by its owner or a superadmin through its own route.
 */
class PublicProfileResource extends JsonResource
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
            'account_type' => $this->account_type,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'is_verified' => (bool) $this->isVerified(),
            'posts_freight' => (bool) $this->posts_freight,
            'provides_transport' => (bool) $this->provides_transport,
            'business_description' => $this->business_description,

            'primary_location' => $this->whenLoaded('primaryLocation', fn () => $this->primaryLocation === null ? null : [
                'id' => (int) $this->primaryLocation->id,
                'name' => (string) $this->primaryLocation->name,
                'region_name' => $this->primaryLocation->region_name,
            ]),

            'member_since' => $this->whenLoaded('user', fn () => $this->user?->created_at?->toIso8601String()),

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

            // Two booleans instead of the documents themselves. The directory
            // and the show page both only need to say whether anything was
            // uploaded and whether anything has been approved; names, types and
            // paths stay private.
            'has_documents' => $this->hasDocuments(),
            'documents_reviewed' => $this->hasApprovedDocuments(),
        ];
    }

    /**
     * Prefer the aggregate count when the query supplied one, so a directory
     * listing does not load every document row for every card.
     */
    private function hasDocuments(): bool
    {
        if ($this->documents_count !== null) {
            return (int) $this->documents_count > 0;
        }

        return $this->relationLoaded('documents') && $this->documents->isNotEmpty();
    }

    private function hasApprovedDocuments(): bool
    {
        if ($this->has_approved_documents !== null) {
            return (bool) $this->has_approved_documents;
        }

        return $this->relationLoaded('documents')
            && $this->documents->contains(fn ($document) => $document->status === 'approved');
    }
}
