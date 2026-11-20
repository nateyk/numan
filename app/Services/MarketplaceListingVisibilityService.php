<?php

namespace App\Services;

use App\Models\FreightPost;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use App\Notifications\FreightPostsHiddenNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MarketplaceListingVisibilityService
{
    public function __construct(
        protected MarketplaceEntitlementService $entitlement,
        protected MarketplaceSettingsService $settings,
    ) {}

    /**
     * Flip lapsed subscriptions to 'expired'.
     *
     * Canceled rows become expired only once their prepaid grace ends.
     * Superseded and already expired rows remain untouched. Nothing is deleted.
     */
    public function expireLapsedSubscriptions(): int
    {
        return MarketplaceSubscription::whereIn('status', ['active', 'trialing', 'canceled'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Restore every hidden post for a workspace after a successful payment.
     *
     * The only thing that ever clears hidden_at.
     */
    public function restoreHiddenPosts(Workspace $workspace): int
    {
        return FreightPost::where('workspace_id', $workspace->id)
            ->hidden()
            ->update(['hidden_at' => null]);
    }

    /**
     * Hide the excess live posts of workspaces whose paid plan lapsed.
     *
     * Only ever SETS hidden_at. It never clears it.
     *
     * @return list<array<string, mixed>> one entry per workspace it acted on
     */
    public function hideExcessPosts(): array
    {
        $freeLimit = $this->settings->get('free_active_posts');
        $keep = $freeLimit === -1 ? PHP_INT_MAX : max(0, $freeLimit);
        $results = [];

        $workspaceIds = MarketplaceSubscription::where('status', 'expired')
            ->distinct()
            ->pluck('workspace_id');

        foreach ($workspaceIds as $workspaceId) {
            $result = $this->processWorkspace(Workspace::find($workspaceId), $keep);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Works out what would happen for every affected workspace without writing
     * anything.
     *
     * Unlike hideExcessPosts() this also considers subscriptions that are
     * lapsed but not yet flipped to 'expired', because a dry run cannot flip
     * them.
     *
     * @return list<array<string, mixed>>
     */
    public function previewLapsedWorkspaces(): array
    {
        $freeLimit = $this->settings->get('free_active_posts');
        $keep = $freeLimit === -1 ? PHP_INT_MAX : max(0, $freeLimit);

        $results = [];

        foreach ($this->lapsedWorkspaceIds() as $workspaceId) {
            $workspace = Workspace::find($workspaceId);

            if (! $workspace) {
                continue;
            }

            $preview = $this->evaluate($workspace, $keep);

            if ($preview !== null) {
                $results[] = $preview;
            }
        }

        return $results;
    }

    /**
     * Works out what would happen for a workspace without writing anything.
     *
     * @return array<string, mixed>|null
     */
    public function previewWorkspace(Workspace $workspace): ?array
    {
        $freeLimit = $this->settings->get('free_active_posts');
        $keep = $freeLimit === -1 ? PHP_INT_MAX : max(0, $freeLimit);

        return $this->evaluate($workspace, $keep);
    }

    /**
     * @return Collection<int, int>
     */
    private function lapsedWorkspaceIds()
    {
        return MarketplaceSubscription::where(function ($query): void {
            $query->where('status', 'expired')
                ->orWhere(function ($inner): void {
                    $inner->whereIn('status', ['active', 'trialing', 'canceled'])
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', now());
                });
        })
            ->distinct()
            ->pluck('workspace_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function processWorkspace(?Workspace $workspace, int $keep): ?array
    {
        if (! $workspace) {
            return null;
        }

        // A Chapa/manual renewal locks this same workspace row before issuing
        // a receipt and restoring hidden posts. Lock it here too and re-check
        // eligibility INSIDE the lock: an expiry sweep can never hide posts
        // after a successful renewal has already restored them.
        $result = DB::transaction(function () use ($workspace, $keep): ?array {
            $lockedWorkspace = Workspace::whereKey($workspace->id)->lockForUpdate()->first();

            if (! $lockedWorkspace) {
                return null;
            }

            $preview = $this->evaluate($lockedWorkspace, $keep);

            if ($preview === null || $preview['would_hide'] === 0) {
                return $preview;
            }

            $now = now();
            $hiddenIds = [];

            foreach ($preview['hide_post_ids'] as $id) {
                if (FreightPost::whereKey($id)->whereNull('hidden_at')->update(['hidden_at' => $now]) > 0) {
                    $hiddenIds[] = $id;
                }
            }

            $preview['hidden'] = $hiddenIds !== [];
            $preview['hidden_post_ids'] = $hiddenIds;

            return $preview;
        });

        // Email/database notification happens after committing the workspace
        // lock. Slow notification delivery must not block a payer's renewal.
        if (($result['hidden'] ?? false) && $this->entitlement->activeSubscription($workspace) === null) {
            $this->notifyOwner($workspace, count($result['hidden_post_ids']), $keep);
        }

        return $result;
    }

    /**
     * Decide what a workspace's visibility should be, writing nothing.
     *
     * @return array<string, mixed>|null
     */
    private function evaluate(Workspace $workspace, int $keep): ?array
    {
        // Lapsed means an already-expired row, or one still marked active whose
        // ends_at has passed. A dry run sees the latter before the real pass
        // flips it.
        $expiredAt = MarketplaceSubscription::where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('status', 'expired')
                    ->orWhere(function ($inner): void {
                        $inner->whereIn('status', ['active', 'trialing', 'canceled'])
                            ->whereNotNull('ends_at')
                            ->where('ends_at', '<=', now());
                    });
            })
            ->max('ends_at');

        // A workspace that never held a paid subscription is grandfathered:
        // nothing is ever hidden from it.
        if ($expiredAt === null) {
            return null;
        }

        // Renewal case (A): an active subscription exists, so nothing is hidden.
        $hasActive = $this->entitlement->activeSubscription($workspace) !== null;

        if ($hasActive) {
            return null;
        }

        $liveStatuses = MarketplaceEntitlementService::LIVE_POST_STATUSES;

        // Hidden posts are not publicly live, so they are excluded (decision 2).
        $visibleLive = $this->ownedPosts($workspace)
            ->whereIn('status', $liveStatuses)
            ->visible()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id']);

        $toHide = $visibleLive->slice($keep)->pluck('id')->values()->all();

        return [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'plan' => MarketplaceSubscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing', 'expired'])
                ->latest('ends_at')
                ->value('billing_interval'),
            'expired_at' => (string) $expiredAt,
            'visible_live_posts' => $visibleLive->count(),
            'keep' => $keep === PHP_INT_MAX ? -1 : $keep,
            'would_hide' => count($toHide),
            'hide_post_ids' => $toHide,
            'hidden' => false,
        ];
    }

    /**
     * Resolve a workspace's posts by workspace_id, falling back to user_id for
     * posts written before workspace_id was backfilled.
     *
     * @return Builder<FreightPost>
     */
    private function ownedPosts(Workspace $workspace)
    {
        return FreightPost::where(function ($query) use ($workspace) {
            $query->where('workspace_id', $workspace->id);

            $query->orWhere(function ($inner) use ($workspace) {
                $inner->whereNull('workspace_id')
                    ->whereIn('user_id', $workspace->users()->pluck('users.id'));
            });
        });
    }

    private function notifyOwner(Workspace $workspace, int $count, int $keep): void
    {
        $owner = $workspace->owner;

        if (! $owner || $count === 0) {
            return;
        }

        try {
            Notification::send($owner, new FreightPostsHiddenNotification(
                $workspace,
                $count,
                $keep === PHP_INT_MAX ? -1 : $keep,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
