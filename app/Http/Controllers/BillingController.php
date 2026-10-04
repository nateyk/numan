<?php

namespace App\Http\Controllers;

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Services\ManualPaymentExpiryService;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceSettingsService;
use App\Services\MarketplaceTextSettingsService;
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

        // Pending Chapa transactions are reconciled by the scheduled ReconcileChapaPayments command

        $marketplaceInvoices = Schema::hasTable('marketplace_payment_receipts')
            ? MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->latest()->take(10)->get()->map(fn ($r) => [
                'id' => $r->invoice_number,
                'date' => $r->issued_at ? $r->issued_at->format('F j, Y') : $r->created_at->format('F j, Y'),
                'total' => (int) ((float) $r->amount_etb * 100),
                'pdf_url' => null,
                'is_marketplace' => true,
                'amount_etb' => $r->amount_etb,
            ])->values()
            : collect();

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
            'marketplaceInvoices' => $marketplaceInvoices,
            'plans' => [],
            'marketplacePlan' => $marketplaceEntitlement->getPlanForWorkspace($workspace),
            'marketplacePlans' => $marketplacePlans,
            'freeLimits' => $this->getFreeLimitsForDisplay(),
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
            'plans' => [],
            'marketplacePlans' => $this->getMarketplacePlansForDisplay(),
            'freeLimits' => $this->getFreeLimitsForDisplay(),
            'currentPlan' => $workspace->plan_key,
            'currentMarketplacePlan' => $marketplaceSub?->plan?->slug ?? $marketplaceEntitlement->getPlanForWorkspace($workspace)['slug'] ?? 'free',
            'currentBillingPeriod' => $workspace->billing_period,
            'currentMarketplaceBillingPeriod' => $marketplaceSub?->billing_interval,
            'recommendedPlan' => $recommendedPlan,
            'recommendedBillingPeriod' => $recommendedBillingPeriod,
            'fromOnboarding' => $request->query('onboarding') === '1',
            'userRole' => $workspace->getUserRole($user),

            // Manual payment sits beside Chapa rather than replacing it. Hidden
            // entirely unless at least one method is fully configured and
            // enabled, so nobody starts a transfer they cannot complete.
            'manualPayment' => [
                'enabled' => app(MarketplaceTextSettingsService::class)->manualPaymentEnabled(),
                'methods' => app(MarketplaceTextSettingsService::class)->availableMethods(),
                'index_url' => route('marketplace.manual.index'),
                'expiry_days' => app(ManualPaymentExpiryService::class)->windowDays(),
            ],
            'marketplaceEntitlement' => [
                'can_post' => $marketplaceEntitlement->canCreateFreightPost($workspace),
                'remaining_posts' => $marketplaceEntitlement->remainingActivePosts($workspace),
                'usage' => $marketplaceEntitlement->usageOverview($workspace),
            ],
        ]);
    }

    /**
     * There is a single paid plan with three durations, so there is nothing
     * to compare side by side. The compare page and the Stripe multi-plan
     * table are kept on disk but unreachable; send visitors to the plans page.
     */
    public function compare(Request $request): RedirectResponse
    {
        return redirect()->route('billing.plans');
    }

    /**
     * Display plan comparison table (legacy Stripe plans).
     */
    public function compareLegacy(Request $request): Response
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

        // Marketplace payments sit in their own table and were invisible here,
        // so a workspace that had paid for a plan in ETB saw an empty history.
        // Both rails are listed together, distinguished by provider, because
        // from the payer's side they are the same decision to make.
        $marketplacePayments = MarketplacePaymentTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('verified_at')
            ->with(['plan:id,name,slug'])
            ->latest('verified_at')
            ->limit(50)
            ->get()
            ->map(fn ($t): array => [
                'id' => $t->id,
                'provider' => $t->provider,
                'status' => $t->status,
                'plan_name' => $t->plan?->name,
                'billing_interval' => $t->billing_interval,
                'amount_etb' => (string) $t->amount_etb,
                'currency' => $t->currency,
                'date' => $t->verified_at?->format('F j, Y'),
                'reference' => $t->manual_reference ?? $t->chapa_ref ?? $t->tx_ref,
                'has_receipt' => $t->receipt !== null,
            ])
            ->values();

        return Inertia::render('Billing/history', [
            'invoices' => $workspace->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->format('F j, Y'),
                'total' => $invoice->total(),
                'pdf_url' => route('billing.invoice.download', $invoice->id),
            ])->values(),
            'marketplacePayments' => $marketplacePayments,
            'manualPaymentIndex' => route('marketplace.manual.index'),
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
        $plans = MarketplacePlan::active()->ordered()->get()
            ->map(fn ($p) => [
                'id' => $p->slug,
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description,
                'price' => ['weekly' => (float) $p->price_weekly_etb, 'monthly' => (float) $p->price_monthly_etb, 'yearly' => (float) $p->price_yearly_etb],
                'price_etb' => ['weekly' => (float) $p->price_weekly_etb, 'monthly' => (float) $p->price_monthly_etb, 'yearly' => (float) $p->price_yearly_etb],
                'features' => $p->features ?? [],
                'limits' => $p->limits ?? [],
                'faq' => $p->faq ?? [],
                'popular' => (bool) $p->is_popular,
            ])->values()->all();

        // Fall back to config when the table is empty (fresh install).
        if ($plans === []) {
            $plans = collect(config('marketplace.plans', []))->map(fn ($plan, $key) => [
                'id' => $key,
                'slug' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'price' => ['weekly' => (float) ($plan['price_weekly_etb'] ?? 0), 'monthly' => (float) ($plan['price_monthly_etb'] ?? 0), 'yearly' => (float) ($plan['price_yearly_etb'] ?? 0)],
                'price_etb' => ['weekly' => (float) ($plan['price_weekly_etb'] ?? 0), 'monthly' => (float) ($plan['price_monthly_etb'] ?? 0), 'yearly' => (float) ($plan['price_yearly_etb'] ?? 0)],
                'features' => $plan['features'] ?? [],
                'limits' => $plan['limits'] ?? [],
                'faq' => $plan['faq'] ?? [],
                'popular' => $plan['popular'] ?? false,
            ])->values()->all();
        }

        return $plans;
    }

    /**
     * Free plan limits for display. Always read from marketplace_settings so
     * an admin change shows up immediately.
     *
     * @return array<string, int>
     */
    protected function getFreeLimitsForDisplay(): array
    {
        return app(MarketplaceSettingsService::class)->all();
    }
}
