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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'display_name' => $this->profile?->display_name ?? $this->name,
            'verification_level' => $this->profile?->verification_level,
            'is_verified' => (bool) $this->profile?->verification_level,
        ];
    }
}
