<?php

namespace App\Services;

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

class MarketplaceEntitlementService
{
    /**
     * Statuses that occupy a slot against the post limit.
     *
     * Counting only "active" lets a Free user queue unlimited posts for
     * review, so anything live in the marketplace counts.
     *
     * @var list<string>
     */
    public const LIVE_POST_STATUSES = [
        FreightPostStatus::Submitted->value,
        FreightPostStatus::UnderReview->value,
        FreightPostStatus::Active->value,
    ];

    public function __construct(
        protected ?MarketplaceSettingsService $settings = null,
    ) {
        $this->settings ??= app(MarketplaceSettingsService::class);
    }

    /**
     * Resolve the active plan for a workspace, or the implicit Free state.
     *
     * @return array{id?: int|null, slug: string, name: string, limits: array<string, mixed>, features: array, subscription?: MarketplaceSubscription}
     */
    public function getPlanForWorkspace(?Workspace $workspace): array
    {
        if (! $workspace) {
            return $this->freePlan();
        }

        $subscription = $this->activeSubscription($workspace);

        if ($subscription?->plan !== null) {
            $plan = $subscription->plan;

            return [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'limits' => $plan->limits ?? [],
                'features' => $plan->features ?? [],
                'subscription' => $subscription,
            ];
        }

        // Paid subscription whose plan row is missing: fall through to Free
        // rather than throwing.
        return $this->freePlan();
    }

    /**
     * The implicit Free state. Limits come from admin-editable settings.
     *
     * @return array{slug: string, name: string, limits: array<string, mixed>, features: array}
     */
    public function freePlan(): array
    {
        return [
            'slug' => 'free',
            'name' => 'Free',
            'limits' => $this->settings->all(),
            'features' => [],
        ];
    }

    public function activeSubscription(Workspace $workspace): ?MarketplaceSubscription
    {
        if (! Schema::hasTable('marketplace_subscriptions')) {
            return null;
        }

        return MarketplaceSubscription::where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('ends_at')
            ->first();
    }

    public function hasPaidSubscription(?Workspace $workspace): bool
    {
        return $workspace !== null && $this->activeSubscription($workspace)?->plan !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLimits(?Workspace $workspace): array
    {
        return $this->getPlanForWorkspace($workspace)['limits'] ?? [];
    }

    /**
     * Resolve a limit, checking the plan limits first and then the free
     * settings, so both the new and legacy key names work.
     */
    public function getLimitValue(?Workspace $workspace, string $key, ?int $default = null): int
    {
        $limits = $this->getLimits($workspace);

        if (array_key_exists($key, $limits)) {
            return (int) $limits[$key];
        }

        $settingKey = $this->settingKeyFor($key);

        if ($settingKey !== null) {
            return $this->settings->get($settingKey);
        }

        return $default ?? -1;
    }

    /**
     * Map a plan limit key to the matching free-settings key.
     */
    private function settingKeyFor(string $key): ?string
    {
        return match ($key) {
            'active_freight_posts', 'free_active_posts' => 'free_active_posts',
            'interests_per_week', 'interests_per_month', 'free_interests_per_week' => 'free_interests_per_week',
            'contact_reveals_per_week', 'free_contact_reveals_per_week' => 'free_contact_reveals_per_week',
            'interest_window_days', 'free_interest_window_days' => 'free_interest_window_days',
            default => null,
        };
    }

    public function canCreateFreightPost(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return true;
        }

        $limit = $this->getLimitValue($workspace, 'active_freight_posts');

        if ($limit === -1) {
            return true;
        }

        return $this->livePostCount($workspace) < $limit;
    }

    public function remainingLivePosts(?Workspace $workspace): int
    {
        if (! $workspace) {
            return -1;
        }

        $limit = $this->getLimitValue($workspace, 'active_freight_posts');

        if ($limit === -1) {
            return -1;
        }

        return max(0, $limit - $this->livePostCount($workspace));
    }

    /**
     * @deprecated Kept as a thin alias. The monthly post cap was removed; the
     *             live post limit is the only post limit.
     */
    public function remainingActivePosts(?Workspace $workspace): int
    {
        return $this->remainingLivePosts($workspace);
    }

    public function livePostCount(?Workspace $workspace): int
    {
        if (! $workspace) {
            return 0;
        }

        return FreightPost::where('workspace_id', $workspace->id)
            ->whereIn('status', self::LIVE_POST_STATUSES)
            ->count();
    }

    /**
     * The rolling interest window, read from an explicit limit key.
     *
     * Never inferred from which limit keys a plan happens to carry: adding an
     * interests_per_month key to a plan must not silently change its window.
     */
    public function interestWindowDays(?Workspace $workspace): int
    {
        $limits = $this->getLimits($workspace);

        $window = $limits['interest_window_days'] ?? null;

        if ($window === null) {
            return $this->settings->get('free_interest_window_days');
        }

        return (int) $window;
    }

    public function canExpressInterest(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return true;
        }

        $limit = $this->getLimitValue($workspace, 'interests_per_week');

        if ($limit === -1) {
            return true;
        }

        return $this->interestCount($workspace, $this->interestWindowDays($workspace)) < $limit;
    }

    public function interestCount(?Workspace $workspace, int $days): int
    {
        if (! $workspace) {
            return 0;
        }

        return FreightInterest::whereIn('user_id', $workspace->users()->pluck('users.id'))
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * @return array<string, array{label: string, count: int, limit: int, percentage: int, remaining: int}>
     */
    public function usageOverview(?Workspace $workspace): array
    {
        if (! $workspace) {
            return [];
        }

        $postLimit = $this->getLimitValue($workspace, 'active_freight_posts');
        $postCount = $this->livePostCount($workspace);

        $interestLimit = $this->getLimitValue($workspace, 'interests_per_week');
        $interestWindow = $this->interestWindowDays($workspace);
        $interestCount = $this->interestCount($workspace, $interestWindow);

        $revealLimit = $this->getLimitValue($workspace, 'contact_reveals_per_week');

        $rows = [
            'active_freight_posts' => [
                'label' => 'Live Freight Posts',
                'count' => $postCount,
                'limit' => $postLimit,
            ],
            'interests_per_week' => [
                'label' => "Interests ({$interestWindow}d)",
                'count' => $interestCount,
                'limit' => $interestLimit,
            ],
            'contact_reveals_per_week' => [
                'label' => 'Contact Reveals',
                'count' => 0,
                'limit' => $revealLimit,
            ],
        ];

        $overview = [];

        foreach ($rows as $key => $row) {
            $limit = $row['limit'];

            $overview[$key] = [
                'label' => $row['label'],
                'count' => $row['count'],
                'limit' => $limit,
                'percentage' => $limit === -1 ? 0 : min(100, (int) round(($row['count'] / max(1, $limit)) * 100)),
                'remaining' => $limit === -1 ? -1 : max(0, $limit - $row['count']),
            ];
        }

        return $overview;
    }

    public function getPlanMessage(?Workspace $workspace): string
    {
        $plan = $this->getPlanForWorkspace($workspace);
        $limit = $this->getLimitValue($workspace, 'active_freight_posts');

        if ($limit === -1) {
            return "Unlimited freight posts on {$plan['name']}.";
        }

        $remaining = $this->remainingLivePosts($workspace);

        return "You can publish {$remaining} more freight post(s) on {$plan['name']}.";
    }
}
