<?php

namespace App\Policies;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\User;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceReadiness;

class FreightInterestPolicy
{
    public function create(User $user, FreightPost $post): bool
    {
        if ($user->id === $post->user_id) {
            return false;
        }

        // Readiness, not approval and not a second carrier record. A complete
        // public profile is enough to express interest; the metered reveal
        // ledger is what protects contact details.
        if (! app(MarketplaceReadiness::class)->for($user)['ready']['provides_transport']) {
            return false;
        }

        if ($post->status !== FreightPostStatus::Active->value) {
            return false;
        }

        $exists = FreightInterest::where('freight_post_id', $post->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [FreightInterestStatus::Rejected->value, FreightInterestStatus::Withdrawn->value])
            ->exists();

        if ($exists) {
            return false;
        }

        $workspace = $user->currentWorkspace;
        if ($workspace) {
            $entitlement = app(MarketplaceEntitlementService::class);
            if (! $entitlement->canExpressInterest($workspace)) {
                return false;
            }
        }

        return true;
    }

    public function withdraw(User $user, FreightInterest $interest): bool
    {
        if ($user->id !== $interest->user_id) {
            return false;
        }

        return in_array($interest->status, [FreightInterestStatus::Submitted->value, FreightInterestStatus::Shortlisted->value], true);
    }

    public function updateStatus(User $user, FreightInterest $interest): bool
    {
        return $user->id === $interest->freightPost->user_id;
    }

    public function view(User $user, FreightInterest $interest): bool
    {
        return $user->id === $interest->user_id || $user->id === $interest->freightPost->user_id || (bool) $user->is_superadmin;
    }
}
