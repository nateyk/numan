<?php

namespace App\Policies;

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceReadiness;

class FreightPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, FreightPost $post): bool
    {
        if ($user && $user->id === $post->user_id) {
            return true;
        }

        if ($user && $user->is_superadmin) {
            return true;
        }

        return $post->status === FreightPostStatus::Active->value;
    }

    public function create(User $user): bool
    {
        if ((bool) $user->is_superadmin) {
            return true;
        }

        if (! app(MarketplaceReadiness::class)->for($user)['ready']['posts_freight']) {
            return false;
        }

        // Marketplace entitlement: check workspace plan limits (if workspace exists)
        $workspace = $user->currentWorkspace;
        if ($workspace) {
            $entitlement = app(MarketplaceEntitlementService::class);
            if (! $entitlement->canCreateFreightPost($workspace)) {
                return false;
            }
        }

        return true;
    }

    public function update(User $user, FreightPost $post): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        return $user->id === $post->user_id
            && $post->status === FreightPostStatus::Draft->value;
    }

    public function submit(User $user, FreightPost $post): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        return $user->id === $post->user_id
            && $post->status === FreightPostStatus::Draft->value;
    }

    public function moderate(User $user): bool
    {
        return (bool) $user->is_superadmin;
    }

    public function delete(User $user, FreightPost $post): bool
    {
        if ((bool) $user->is_superadmin) {
            return true;
        }

        if ($user->id !== $post->user_id) {
            return false;
        }

        // Owners may only remove drafts and finished posts. Live posts
        // (submitted / under review / active) carry transporter interests and
        // marketplace visibility, so deleting them would silently destroy
        // other users' records.
        return in_array($post->status, [
            FreightPostStatus::Draft->value,
            FreightPostStatus::Rejected->value,
            FreightPostStatus::Closed->value,
            FreightPostStatus::Expired->value,
        ], true);
    }
}
