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

        return $post->status === FreightPostStatus::Active->value
            && ! $post->isPastPickupWindow();
    }

    /**
     * Management wizard pages may be read by their owner at any stage,
     * including when a paid-plan listing is hidden. Editing remains draft-only.
     */
    public function viewWizard(User $user, FreightPost $post): bool
    {
        return $user->id === $post->user_id || (bool) $user->is_superadmin;
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

    public function close(User $user, FreightPost $post): bool
    {
        return $user->id === $post->user_id
            && in_array($post->status, [
                FreightPostStatus::Submitted->value,
                FreightPostStatus::UnderReview->value,
                FreightPostStatus::Active->value,
                FreightPostStatus::Closed->value,
            ], true);
    }

    public function markArrived(User $user, FreightPost $post): bool
    {
        return $user->id === $post->user_id
            && $post->status === FreightPostStatus::Closed->value
            && $post->closure_reason !== 'cancelled';
    }

    public function delete(User $user, FreightPost $post): bool
    {
        if ((bool) $user->is_superadmin) {
            return true;
        }

        if ($user->id !== $post->user_id) {
            return false;
        }

        // Preserve interested transporters, chosen connections and delivery
        // history even after the listing has closed.
        if ($post->arrived_at !== null || $post->interests()->exists()) {
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
