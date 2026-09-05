<?php

namespace App\Http\Controllers;

use App\Models\MarketplacePlan;
use App\Services\MarketplaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    /**
     * Display the billing overview page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $subscription = $workspace->subscription('default');

        $marketplaceEntitlement = app(MarketplaceEntitlementService::class);
        $marketplaceSub = $workspace->activeMarketplaceSubscription();
        $marketplacePlans = $this->getMarketplacePlansForDisplay();

        return Inertia::render('Billing/index', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->plan_name,
                'on_trial' => $workspace->onTrial('default'),
                'trial_ends_at' => $subscription?->trial_ends_at,
                'seat_count' => $workspace->activeSeatCount(),
                'seat_limit' => $workspace->seatLimit(),
                'marketplace_plan' => $marketplaceEntitlement->getPlanForWorkspace($workspace),
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at,
                'on_grace_period' => $subscription->onGracePeriod(),
                'cancelled' => $subscription->canceled(),
            ] : null,
            'marketplaceSubscription' => $marketplaceSub ? [
                'id' => $marketplaceSub->id,
                'plan' => $marketplaceSub->plan?->name ?? $marketplaceSub->plan_id,
                'status' => $marketplaceSub->status,
                'billing_interval' => $marketplaceSub->billing_interval,
                'current_period_ends_at' => $marketplaceSub->current_period_ends_at,
                'ends_at' => $marketplaceSub->ends_at,
            ] : null,
            'upcoming_invoice' => Inertia::defer(function () use ($workspace, $subscription) {
                if ($subscription && $subscription->active()) {
                    try {
                        $invoice = $workspace->upcomingInvoice();
                        if ($invoice) {
                            return [
                                'amount' => $invoice->total(),
                                'date' => $invoice->date()->format('F j, Y'),
                            ];
                        }
                    } catch (\Exception) {
                        // No upcoming invoice
                    }
                }

                return null;
            }),
            'usage' => Inertia::defer(fn () => $workspace->usageOverview()),
            'marketplaceUsage' => Inertia::defer(fn () => $marketplaceEntitlement->usageOverview($workspace)),
            'marketplaceEntitlement' => Inertia::defer(fn () => [
                'can_post' => $marketplaceEntitlement->canCreateFreightPost($workspace),
                'can_interest' => $marketplaceEntitlement->canExpressInterest($workspace),
                'remaining_posts' => $marketplaceEntitlement->remainingActivePosts($workspace),
                'message' => $marketplaceEntitlement->getPlanMessage($workspace),
            ]),
            'invoices' => Inertia::defer(function () use ($workspace) {
                return $workspace->invoices()->take(10)->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'date' => $invoice->date()->format('F j, Y'),
                    'total' => $invoice->total(),
                    'pdf_url' => route('billing.invoice.download', $invoice->id),
                ])->values();
            }),
            'plans' => $this->getPlansForDisplay(),
            'marketplacePlans' => $marketplacePlans,
            'userRole' => $workspace->getUserRole($user),
        ]);
    }

    /**
     * Display available plans.
     */
    public function plans(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $recommendedPlan = $request->query('recommended_plan');
        if (! in_array($recommendedPlan, ['pro', 'business'], true)) {
            $recommendedPlan = null;
        }

        $recommendedBillingPeriod = $request->query('recommended_billing_period');
        if (! in_array($recommendedBillingPeriod, ['monthly', 'yearly'], true)) {
            $recommendedBillingPeriod = null;
        }

        $marketplaceEntitlement = app(MarketplaceEntitlementService::class);
        $marketplaceSub = $workspace->activeMarketplaceSubscription();

        return Inertia::render('Billing/plans', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->plan_name,
                'marketplace_plan' => $marketplaceEntitlement->getPlanForWorkspace($workspace),
            ],
            'plans' => $this->getPlansForDisplay(),
            'marketplacePlans' => $this->getMarketplacePlansForDisplay(),
            'currentPlan' => $workspace->plan_key,
            'currentMarketplacePlan' => $marketplaceSub?->plan?->slug ?? $marketplaceEntitlement->getPlanForWorkspace($workspace)['slug'] ?? 'free',
            'currentBillingPeriod' => $workspace->billing_period,
            'currentMarketplaceBillingPeriod' => $marketplaceSub?->billing_interval,
            'recommendedPlan' => $recommendedPlan,
            'recommendedBillingPeriod' => $recommendedBillingPeriod,
            'fromOnboarding' => $request->query('onboarding') === '1',
            'userRole' => $workspace->getUserRole($user),
            'marketplaceEntitlement' => [
                'can_post' => $marketplaceEntitlement->canCreateFreightPost($workspace),
                'remaining_posts' => $marketplaceEntitlement->remainingActivePosts($workspace),
                'usage' => $marketplaceEntitlement->usageOverview($workspace),
            ],
        ]);
    }

    /**
     * Display plan comparison table.
     */
    public function compare(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        return Inertia::render('Billing/compare', [
            'plans' => $this->getPlansForDisplay(),
            'currentPlan' => $workspace->plan_key,
            'userRole' => $workspace->getUserRole($user),
        ]);
    }

    /**
     * Display billing history with all invoices.
     */
    public function history(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        return Inertia::render('Billing/history', [
            'invoices' => $workspace->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->format('F j, Y'),
                'total' => $invoice->total(),
                'pdf_url' => route('billing.invoice.download', $invoice->id),
            ])->values(),
        ]);
    }

    /**
     * Subscribe the workspace to a plan.
     */
    public function subscribe(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:pro,business'],
            'billing_period' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        $user = $request->user();
        $workspace = $user->currentWorkspace;
        Gate::authorize('manageBilling', $workspace);

        $plans = config('billing.plans');

        $plan = $plans[$validated['plan']] ?? null;
        if (! $plan) {
            return response()->json(['error' => 'Invalid plan selected.'], 400);
        }

        $priceId = $plan['stripe_price_id'][$validated['billing_period']] ?? null;
        if (! $priceId) {
            return response()->json(['error' => 'Invalid billing period.'], 400);
        }

        // Check if already on this exact plan and billing period
        $subscription = $workspace->subscription('default');
        if ($subscription && $subscription->stripe_price === $priceId) {
            return response()->json([
                'success' => true,
                'message' => 'You are already on this plan.',
            ]);
        }

        // If already subscribed (active or trialing), swap to new price
        if ($workspace->subscribed('default') || $workspace->onTrial('default')) {
            try {
                $workspace->subscription('default')->swap($priceId);

                return response()->json([
                    'success' => true,
                    'message' => 'Your subscription has been updated.',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update subscription: '.$e->getMessage(),
                ], 500);
            }
        }

        // No existing subscription - create checkout session
        try {
            $checkout = $workspace->newSubscription('default', $priceId)
                ->trialDays(config('billing.trial_days', 0))
                ->checkout([
                    'success_url' => route('billing.index').'?checkout=success',
                    'cancel_url' => route('billing.plans').'?checkout=cancelled',
                ]);

            return response()->json(['checkout_url' => $checkout->url]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create checkout: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redirect to Stripe Customer Portal.
     */
    public function portal(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        Gate::authorize('manageBilling', $workspace);

        if (! $workspace->hasStripeId()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'No billing account found. Please subscribe to a plan first.'], 400);
            }

            return redirect()->back()
                ->with('error', 'No billing account found. Please subscribe to a plan first.');
        }

        $portalUrl = $workspace->billingPortalUrl(route('billing.index'));

        // Return URL for frontend to redirect (avoids CORS issues)
        if ($request->wantsJson()) {
            return response()->json(['portal_url' => $portalUrl]);
        }

        return redirect($portalUrl);
    }

    /**
     * Download the specified invoice as a PDF.
     */
    public function downloadInvoice(Request $request, string $invoiceId): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;
        Gate::authorize('manageBilling', $workspace);

        return $workspace->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }

    /**
     * Cancel the workspace subscription (downgrade to free).
     */
    public function cancel(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        Gate::authorize('manageBilling', $workspace);

        if (! $workspace->subscribed('default')) {
            return response()->json([
                'success' => false,
                'error' => 'No active subscription to cancel.',
            ], 400);
        }

        try {
            // Cancel at end of billing period (grace period)
            $workspace->subscription('default')->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Your subscription has been cancelled. You will have access until the end of your billing period.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel subscription: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume a cancelled subscription during grace period.
     */
    public function resume(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        Gate::authorize('manageBilling', $workspace);

        $subscription = $workspace->subscription('default');

        if (! $subscription || ! $subscription->onGracePeriod()) {
            return response()->json([
                'success' => false,
                'error' => 'No subscription to resume.',
            ], 400);
        }

        try {
            $subscription->resume();

            return response()->json([
                'success' => true,
                'message' => 'Your subscription has been resumed.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to resume subscription: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Stripe webhooks.
     */
    public function webhook(Request $request): SymfonyResponse
    {
        // Use Laravel Cashier's webhook controller
        $controller = new WebhookController;

        return $controller->handleWebhook($request);
    }

    /**
     * Get plans formatted for frontend display (Stripe).
     */
    protected function getPlansForDisplay(): array
    {
        $plans = config('billing.plans');

        return collect($plans)->map(function ($plan, $key) {
            return [
                'id' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'price' => $plan['price'],
                'features' => $plan['features'],
                'limits' => $plan['limits'],
                'popular' => $plan['popular'] ?? false,
            ];
        })->values()->all();
    }

    protected function getMarketplacePlansForDisplay(): array
    {
        if (Schema::hasTable('marketplace_plans')) {
            $dbPlans = MarketplacePlan::active()->ordered()->get();
            if ($dbPlans->isNotEmpty()) {
                return $dbPlans->map(fn ($p) => [
                    'id' => $p->slug,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'description' => $p->description,
                    'price' => ['monthly' => (float) $p->price_monthly_etb, 'yearly' => (float) $p->price_yearly_etb],
                    'price_etb' => ['monthly' => (float) $p->price_monthly_etb, 'yearly' => (float) $p->price_yearly_etb],
                    'features' => $p->features ?? [],
                    'limits' => $p->limits ?? [],
                    'faq' => $p->faq ?? [],
                    'popular' => (bool) $p->is_popular,
                ])->values()->all();
            }
        }

        $plans = config('marketplace.plans', config('billing.plans'));

        return collect($plans)->map(function ($plan, $key) {
            // Normalize ETB prices
            $monthly = $plan['price_monthly_etb'] ?? $plan['price']['monthly'] ?? 0;
            $yearly = $plan['price_yearly_etb'] ?? $plan['price']['yearly'] ?? 0;

            return [
                'id' => $key,
                'slug' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'price' => ['monthly' => (float) $monthly, 'yearly' => (float) $yearly],
                'price_etb' => ['monthly' => (float) $monthly, 'yearly' => (float) $yearly],
                'features' => $plan['features'] ?? [],
                'limits' => $plan['limits'] ?? [],
                'faq' => $plan['faq'] ?? [],
                'popular' => $plan['popular'] ?? false,
            ];
        })->values()->all();
    }
}
