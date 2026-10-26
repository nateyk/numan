<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Services\MarketplaceSettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplacePlanController extends Controller
{
    /**
     * Limit keys an admin may edit on a plan. Team, workspace, API key and
     * webhook quotas belong to the Stripe PlanLimitService, not to
     * marketplace plans. interest_window_days is deliberately absent: the
     * rolling interest window must not be editable, because changing it
     * silently changes how far back existing interests are counted.
     */
    private const EDITABLE_LIMIT_KEYS = [
        'active_freight_posts',
        'interests_per_week',
        'contact_reveals',
    ];

    public function index(Request $request): Response
    {
        $plans = MarketplacePlan::ordered()->withTrashed()->get()->map(fn ($p) => [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'description' => $p->description,
            'price_weekly_etb' => $p->price_weekly_etb,
            'price_monthly_etb' => $p->price_monthly_etb,
            'price_yearly_etb' => $p->price_yearly_etb,
            'is_active' => $p->is_active,
            'is_popular' => $p->is_popular,
            'sort_order' => $p->sort_order,
            'limits' => $p->limits,
            'features' => $p->features,
            'faq' => $p->faq,
            'subscription_count' => MarketplaceSubscription::where('plan_id', $p->id)->count(),
            'deleted_at' => $p->deleted_at,
        ]);

        return Inertia::render('admin/marketplace-plans', [
            'plans' => $plans,
            'freeLimits' => app(MarketplaceSettingsService::class)->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        MarketplacePlan::create($data + ['is_active' => true]);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, MarketplacePlan $marketplacePlan)
    {
        $data = $this->validated($request, $marketplacePlan);

        // Limits are stored as JSON. Only the editable keys are accepted from the
        // request; keys that are not editable (interest_window_days) are
        // preserved from the existing row rather than dropped, so an admin
        // cannot break interest counting by editing other limits.
        if (array_key_exists('limits', $data)) {
            $data['limits'] = array_merge(
                $marketplacePlan->limits ?? [],
                array_intersect_key($data['limits'] ?? [], array_flip(self::EDITABLE_LIMIT_KEYS))
            );
        }

        $marketplacePlan->update($data);

        return back()->with('success', 'Plan updated.');
    }

    /**
     * Toggle whether a plan is offered. Archiving is preferred over deleting
     * because existing subscriptions still reference the plan row.
     */
    public function toggle(Request $request, MarketplacePlan $marketplacePlan)
    {
        $marketplacePlan->update(['is_active' => ! $marketplacePlan->is_active]);

        return back()->with('success', $marketplacePlan->is_active
            ? 'Plan is now offered.'
            : 'Plan archived. Existing subscribers keep access until their end date.');
    }

    /**
     * Deleting a plan that subscribers still reference would strip their
     * entitlements, so this refuses instead.
     */
    public function destroy(MarketplacePlan $marketplacePlan)
    {
        $subscriptionCount = MarketplaceSubscription::where('plan_id', $marketplacePlan->id)->count();

        if ($subscriptionCount > 0) {
            return back()->with('error', sprintf(
                'Cannot delete "%s": %d subscription(s) still reference it. Archive it instead — subscribers keep their access until their end date.',
                $marketplacePlan->name,
                $subscriptionCount
            ));
        }

        $marketplacePlan->delete();

        return back()->with('success', 'Plan archived.');
    }

    public function restore(int $id)
    {
        $plan = MarketplacePlan::withTrashed()->findOrFail($id);
        $plan->restore();

        return back()->with('success', 'Plan restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MarketplacePlan $plan = null): array
    {
        $uniqueSlug = 'unique:marketplace_plans,slug'.($plan ? ','.$plan->id : '');

        return $request->validate([
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', $uniqueSlug],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_weekly_etb' => ['nullable', 'numeric', 'min:0'],
            'price_monthly_etb' => ['required', 'numeric', 'min:0'],
            'price_yearly_etb' => ['required', 'numeric', 'min:0'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'limits.active_freight_posts' => ['nullable', 'integer', 'min:-1'],
            'limits.interests_per_week' => ['nullable', 'integer', 'min:-1'],
            'limits.contact_reveals' => ['nullable', 'integer', 'min:-1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'faq' => ['nullable', 'array'],
            'faq.*.q' => ['required_with:faq', 'string', 'max:255'],
            'faq.*.a' => ['required_with:faq', 'string', 'max:1000'],
        ]);
    }
}
