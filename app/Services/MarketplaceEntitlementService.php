<?php

namespace App\Services;

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

class MarketplaceEntitlementService
{
    /**
     * Resolve active plan for workspace (DB first, fallback to config/marketplace.php)
     */
    public function getPlanForWorkspace(?Workspace $workspace): array
    {
        if (! $workspace) {
            return $this->fallbackPlan('free');
        }

        // Try active marketplace subscription
        if (Schema::hasTable('marketplace_subscriptions') && Schema::hasTable('marketplace_plans')) {
            $sub = MarketplaceSubscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest()
                ->first();

            if ($sub && $sub->plan) {
                return [
                    'id' => $sub->plan->id,
                    'slug' => $sub->plan->slug,
                    'name' => $sub->plan->name,
                    'limits' => $sub->plan->limits ?? [],
                    'features' => $sub->plan->features ?? [],
                    'subscription' => $sub,
                ];
            }

            // Try DB plan by slug from plan_override or resolved plan key
            $slug = strtolower($workspace->plan_override ?? $workspace->plan_key ?? 'free');
            $dbPlan = MarketplacePlan::where('slug', $slug)->where('is_active', true)->first();
            if ($dbPlan) {
                return [
                    'id' => $dbPlan->id,
                    'slug' => $dbPlan->slug,
                    'name' => $dbPlan->name,
                    'limits' => $dbPlan->limits ?? [],
                    'features' => $dbPlan->features ?? [],
                ];
            }
        }

        // Fallback to config/marketplace.php OR legacy billing
        $marketplacePlans = config('marketplace.plans');
        if ($marketplacePlans) {
            $key = strtolower($workspace->plan_override ?? $workspace->plan_key ?? 'free');
            if (isset($marketplacePlans[$key])) {
                $p = $marketplacePlans[$key];

                return [
                    'slug' => $key,
                    'name' => $p['name'],
                    'limits' => $p['limits'] ?? [],
                    'features' => $p['features'] ?? [],
                ];
            }
        }

        // Legacy Stripe billing fallback
        return $this->fallbackPlan(strtolower($workspace->plan_key ?? 'free'));
    }

    protected function fallbackPlan(string $key): array
    {
        $config = config('marketplace.plans.'.$key) ?? config('billing.plans.'.$key) ?? config('marketplace.plans.free');
        if (! $config) {
            $config = ['name' => 'Free', 'limits' => ['active_freight_posts' => 3, 'monthly_posts' => 5, 'interests_per_month' => 5], 'features' => []];
        }

        return [
            'slug' => $key,
            'name' => $config['name'] ?? ucfirst($key),
            'limits' => $config['limits'] ?? [],
            'features' => $config['features'] ?? [],
        ];
    }

    public function getLimits(?Workspace $workspace): array
    {
        return $this->getPlanForWorkspace($workspace)['limits'] ?? [];
    }

    public function getLimitValue(?Workspace $workspace, string $key, int $default = 3): int
    {
        $limits = $this->getLimits($workspace);

        return (int) ($limits[$key] ?? $default);
    }

    public function canCreateFreightPost(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return true; // allow during onboarding, will be checked via user fallback
        }

        $limit = $this->getLimitValue($workspace, 'active_freight_posts', 3);
        if ($limit === -1) {
            return true;
        }

        $active = FreightPost::where('workspace_id', $workspace->id)->where('status', 'Active')->count();
        // Fallback to user-owned if workspace_id not yet backfilled
        if ($active === 0) {
            $active = FreightPost::where('user_id', $workspace->owner_id)->where('status', 'Active')->count();
        }

        return $active < $limit;
    }

    public function canCreateMonthlyPost(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return true;
        }

        $limit = $this->getLimitValue($workspace, 'monthly_posts', 5);
        if ($limit === -1) {
            return true;
        }

        $monthly = FreightPost::where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return $monthly < $limit;
    }

    public function canExpressInterest(?Workspace $workspace): bool
    {
        if (! $workspace) {
            return true;
        }

        $limit = $this->getLimitValue($workspace, 'interests_per_month', 5);
        if ($limit === -1) {
            return true;
        }

        // Count freight interests by users in workspace last 30 days
        $userIds = $workspace->users()->pluck('users.id');
        $count = FreightInterest::whereIn('user_id', $userIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return $count < $limit;
    }

    public function remainingActivePosts(?Workspace $workspace): int
    {
        $limit = $this->getLimitValue($workspace, 'active_freight_posts', 3);
        if ($limit === -1) {
            return -1;
        }
        $active = $workspace ? FreightPost::where('workspace_id', $workspace->id)->where('status', 'Active')->count() : 0;

        return max(0, $limit - $active);
    }

    public function usageOverview(?Workspace $workspace): array
    {
        if (! $workspace) {
            return [];
        }

        $limits = $this->getLimits($workspace);
        $overview = [];

        $map = [
            'active_freight_posts' => ['label' => 'Active Freight Posts', 'count' => FreightPost::where('workspace_id', $workspace->id)->where('status', 'Active')->count()],
            'monthly_posts' => ['label' => 'Posts (30d)', 'count' => FreightPost::where('workspace_id', $workspace->id)->where('created_at', '>=', now()->subDays(30))->count()],
            'interests_per_month' => ['label' => 'Interests (30d)', 'count' => FreightInterest::whereIn('user_id', $workspace->users()->pluck('users.id'))->where('created_at', '>=', now()->subDays(30))->count()],
        ];

        foreach ($map as $key => $data) {
            $limit = $limits[$key] ?? null;
            if ($limit === null) {
                continue;
            }
            $count = $data['count'];
            $percentage = $limit === -1 || $limit === 0 ? 0 : (int) round(($count / max(1, $limit)) * 100);
            $overview[$key] = [
                'label' => $data['label'],
                'count' => $count,
                'limit' => $limit,
                'percentage' => $percentage,
                'remaining' => $limit === -1 ? -1 : max(0, $limit - $count),
            ];
        }

        return $overview;
    }

    public function getPlanMessage(?Workspace $workspace): string
    {
        $plan = $this->getPlanForWorkspace($workspace);
        $limits = $plan['limits'] ?? [];
        $activeLimit = $limits['active_freight_posts'] ?? 3;

        if ($activeLimit === -1) {
            return 'Unlimited freight posts on '.$plan['name'].' plan.';
        }

        $remaining = $this->remainingActivePosts($workspace);

        return "You can create {$remaining} more active post(s) on {$plan['name']} ({$activeLimit} limit).";
    }
}
