<?php

namespace App\Policies;

use App\Models\ContactReveal;
use App\Models\Profile;
use App\Models\User;

/**
 * Who may see a public profile, edit it, or read its documents.
 *
 * Visibility is separate from editing on purpose. An approved carrier profile is
 * public, and a viewer already in the reveal ledger may see a consignor's page,
 * but neither of them may manage it. The management controller authorises
 * `update`, so a stranger cannot open somebody else's page through it.
 */
class ProfilePolicy
{
    public function view(?User $user, Profile $profile): bool
    {
        // An approved carrier is the public directory entry. Anyone, including a
        // guest, may read it.
        if ($profile->provides_transport && $profile->isApproved()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->id === $profile->user_id || $user->is_superadmin) {
            return true;
        }

        return ContactReveal::hasPair($user->id, $profile->user_id);
    }

    public function update(User $user, Profile $profile): bool
    {
        return $user->id === $profile->user_id;
    }

    public function downloadDocument(User $user, Profile $profile): bool
    {
        return $user->id === $profile->user_id || (bool) $user->is_superadmin;
    }

    public function moderate(User $user): bool
    {
        return (bool) $user->is_superadmin;
    }
}
