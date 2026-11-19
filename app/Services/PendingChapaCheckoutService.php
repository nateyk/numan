<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use App\Models\User;
use App\Models\Workspace;

/**
 * The same unresolved Chapa attempt must be checked rather than silently
 * starting a second payment after a timeout, redirect, or browser reload.
 *
 * This is a safety window for recent attempts, not a payment status decision:
 * only provider verification may activate a subscription.
 */
class PendingChapaCheckoutService
{
    public function find(Workspace $workspace, User $user): ?MarketplacePaymentTransaction
    {
        return MarketplacePaymentTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
            ->whereIn('status', ['pending', 'initialized'])
            ->where('created_at', '>=', now()->subDays(2))
            ->latest('id')
            ->first();
    }
}
