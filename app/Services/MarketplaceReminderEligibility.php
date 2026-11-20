<?php

namespace App\Services;

use App\Models\MarketplaceSubscription;

/**
 * Check whether a claimed reminder is still appropriate at delivery time.
 * A renewal can happen between claim creation and recovery after an outage.
 */
class MarketplaceReminderEligibility
{
    public function isStillDue(MarketplaceSubscription $subscription, string $type): bool
    {
        if (! in_array($type, MarketplaceReminderWindow::due($subscription, now())['types'], true)) {
            return false;
        }

        return $type !== MarketplaceReminderWindow::EXPIRED
            || ! $this->hasNewerPaidPeriod($subscription);
    }

    /**
     * A newer paid term supersedes an older expiry, even if the newer period
     * also subsequently ended. An unsettled checkout cannot suppress notices.
     */
    public function hasNewerPaidPeriod(MarketplaceSubscription $subscription): bool
    {
        return MarketplaceSubscription::query()
            ->where('workspace_id', $subscription->workspace_id)
            ->where('id', '>', $subscription->id)
            ->whereIn('status', ['active', 'trialing', 'canceled', 'expired'])
            ->where(function ($query) use ($subscription) {
                $query->where('ends_at', '>=', $subscription->ends_at)
                    ->orWhere(function ($active) {
                        $active->whereNull('ends_at')->whereIn('status', ['active', 'trialing']);
                    });
            })
            ->exists();
    }
}
