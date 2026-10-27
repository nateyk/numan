<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public owner summary attached to a freight post.
 *
 * Exposes a display name and verification only. The owner's email address, and
 * the profile's phone number, are never included.
 */
class PostOwnerSummary extends JsonResource
{
    /**
     * The owner flattened to the exact shape the freight pages read.
     *
     * Resolving this resource through FreightPostResource nests it under a
     * "data" key, so post.owner.id reads as undefined and the page's isOwnPost
     * comparison treats every owner — including one viewing their own load — as
     * a visitor. Resolve through this method instead of the resource so the
     * payload carries a flat owner object.
     *
     * @return array<string, mixed>
     */
    public static function flat(object $user): array
    {
        return (new self($user))->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'display_name' => $this->profile?->display_name ?? $this->name,
            'is_verified' => $this->profile?->verification_status === 'verified',
        ];
    }
}
