<?php

namespace App\Policies;

use App\Models\TransporterProfile;
use App\Models\User;

class TransporterProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransporterProfile $profile): bool
    {
        if ($user->id === $profile->user_id) {
            return true;
        }

        if ($user->is_superadmin) {
            return true;
        }

        // Public directory only approved profiles are viewable by anyone
        return $profile->isApproved();
    }

    public function create(User $user): bool
    {
        return $user->isProfileOnboarded();
    }

    public function update(User $user, TransporterProfile $profile): bool
    {
        if ($user->id === $profile->user_id) {
            return in_array($profile->status, ['draft', 'pending', 'rejected'], true);
        }

        return (bool) $user->is_superadmin;
    }

    public function delete(User $user, TransporterProfile $profile): bool
    {
        return $user->id === $profile->user_id || (bool) $user->is_superadmin;
    }

    public function moderate(User $user): bool
    {
        return (bool) $user->is_superadmin;
    }

    public function downloadDocument(User $user, TransporterProfile $profile): bool
    {
        return $user->id === $profile->user_id || (bool) $user->is_superadmin;
    }

    public function viewDirectory(User $user, TransporterProfile $profile): bool
    {
        return $profile->isApproved();
    }
}
