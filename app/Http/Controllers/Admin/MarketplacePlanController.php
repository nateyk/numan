<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplacePlanController extends Controller
{
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
            'deleted_at' => $p->deleted_at,
        ]);

        return Inertia::render('admin/marketplace-plans', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:marketplace_plans,slug'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_weekly_etb' => ['nullable', 'numeric', 'min:0'],
            'price_monthly_etb' => ['required', 'numeric', 'min:0'],
            'price_yearly_etb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'limits.active_freight_posts' => ['nullable', 'integer'],
            'limits.monthly_posts' => ['nullable', 'integer'],
            'limits.interests_per_month' => ['nullable', 'integer'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'faq' => ['nullable', 'array'],
            'faq.*.q' => ['required_with:faq', 'string', 'max:255'],
            'faq.*.a' => ['required_with:faq', 'string', 'max:1000'],
        ]);

        MarketplacePlan::create($data);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, MarketplacePlan $marketplacePlan)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', 'unique:marketplace_plans,slug,'.$marketplacePlan->id],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_weekly_etb' => ['nullable', 'numeric', 'min:0'],
            'price_monthly_etb' => ['required', 'numeric', 'min:0'],
            'price_yearly_etb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'faq' => ['nullable', 'array'],
            'faq.*.q' => ['required_with:faq', 'string', 'max:255'],
            'faq.*.a' => ['required_with:faq', 'string', 'max:1000'],
        ]);

        $marketplacePlan->update($data);

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(MarketplacePlan $marketplacePlan)
    {
        $marketplacePlan->delete();

        return back()->with('success', 'Plan archived.');
    }

    public function restore(int $id)
    {
        $plan = MarketplacePlan::withTrashed()->findOrFail($id);
        $plan->restore();

        return back()->with('success', 'Plan restored.');
    }
}
