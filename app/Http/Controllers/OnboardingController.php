<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\LegalForm;
use App\Enums\OnboardingStep;
use App\Models\Location;
use App\Models\OnboardingStepLog;
use App\Models\Profile;
use App\Services\PhoneNumberService;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        protected PhoneNumberService $phones,
    ) {}

    /**
     * Display the NumanFreight 3-step onboarding wizard (public profile).
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Legacy workspace onboarded check — keep for BC, but also check new public profile
        //
        // An onboarded user has no business in a wizard whose every action would
        // refuse them, so they used to land on the dashboard with no way to change
        // what they do here. Sent to the page that does let them, which is the
        // same one holding their capabilities and what is left to fill in.
        if ($user->onboarded_at !== null || $user->isProfileOnboarded()) {
            return redirect()->route('settings.marketplace-details.edit');
        }

        // Ensure a profile row exists for every user (idempotent)
        /** @var Profile $profile */
        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'onboarding_step' => OnboardingStep::Capabilities->value,
                'posts_freight' => false,
                'provides_transport' => false,
            ]
        );

        // If user hit index without explicit profile step, ensure it has a step
        if (! $profile->onboarding_step) {
            $profile->forceFill(['onboarding_step' => OnboardingStep::Capabilities->value])->save();
        }

        $currentStep = $profile->currentStep();

        $this->logStep($user->id, $currentStep, 'viewed');

        $locations = Location::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'region_name']);

        return Inertia::render('onboarding/index', [
            'profile' => [
                'account_type' => $profile->account_type,
                'display_name' => $profile->display_name,
                'phone' => $profile->phone,
                'posts_freight' => (bool) $profile->posts_freight,
                'provides_transport' => (bool) $profile->provides_transport,
                'onboarding_step' => $profile->onboarding_step,
                'primary_location_id' => $profile->primary_location_id,
                'legal_name' => $profile->legal_name,
                'legal_form' => $profile->legal_form,
            ],
            'currentStep' => $currentStep,
            'locations' => $locations,
        ]);
    }

    /**
     * Step 1 — capabilities (multi-select, at least one).
     */
    public function storeCapabilities(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($user->onboarded_at !== null || $user->isProfileOnboarded())) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'posts_freight' => ['required', 'boolean'],
            'provides_transport' => ['required', 'boolean'],
        ]);

        if (! $validated['posts_freight'] && ! $validated['provides_transport']) {
            return back()->withErrors(['posts_freight' => 'Choose at least one.'])->withInput();
        }

        /** @var Profile $profile */
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);

        $profile->forceFill([
            'posts_freight' => (bool) $validated['posts_freight'],
            'provides_transport' => (bool) $validated['provides_transport'],
            'onboarding_step' => OnboardingStep::AccountType->value,
        ])->save();

        $this->logStep($request->user()->id, OnboardingStep::Capabilities->value, 'completed');
        $this->logStep($request->user()->id, OnboardingStep::AccountType->value, 'viewed');

        return back()->with('success', 'Preferences saved.');
    }

    /**
     * Step 2 — account_type (personal | business, single select).
     */
    public function storeAccountType(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($user->onboarded_at !== null || $user->isProfileOnboarded())) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'account_type' => ['required', Rule::in(AccountType::values())],
        ]);

        /** @var Profile $profile */
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);

        // Retain capabilities check — enforce step order
        if (! $profile->hasCapabilities()) {
            return redirect()->route('onboarding.index')->withErrors(['account_type' => 'Complete capabilities first.']);
        }

        $profile->forceFill([
            'account_type' => $validated['account_type'],
            'onboarding_step' => OnboardingStep::Details->value,
        ])->save();

        $this->logStep($request->user()->id, OnboardingStep::AccountType->value, 'completed');
        $this->logStep($request->user()->id, OnboardingStep::Details->value, 'viewed');

        return back()->with('success', 'Account type saved.');
    }

    /**
     * Step 3 — details (display_name, phone, primary_location_id, + business fields).
     */
    public function storeDetails(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($user->onboarded_at !== null || $user->isProfileOnboarded())) {
            return redirect()->route('dashboard');
        }

        /** @var Profile|null $profile */
        $profile = $request->user()->profile()->first();

        if (! $profile || ! $profile->hasCapabilities() || ! $profile->account_type) {
            return redirect()->route('onboarding.index')->withErrors(['display_name' => 'Complete previous steps first.']);
        }

        $isBusiness = $profile->account_type === AccountType::Business->value;

        $rules = [
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => $this->phones->rules(required: true),
            'primary_location_id' => ['required', 'integer', 'exists:locations,id'],
        ];

        if ($isBusiness) {
            $rules['legal_name'] = ['required', 'string', 'max:255'];
            $rules['legal_form'] = ['required', Rule::in(LegalForm::values())];
            $rules['legal_form_other'] = ['nullable', 'string', 'max:255', 'required_if:legal_form,Other'];
        } else {
            $rules['legal_name'] = ['nullable', 'string', 'max:255'];
            $rules['legal_form'] = ['nullable', Rule::in(LegalForm::values())];
            $rules['legal_form_other'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules, [
            'phone' => PhoneNumberService::INVALID_MESSAGE,
        ]);

        $phone = $this->phones->normalize($validated['phone']);

        if ($phone === null) {
            return back()
                ->withErrors(['phone' => PhoneNumberService::INVALID_MESSAGE])
                ->withInput();
        }

        $profile->forceFill([
            'display_name' => $validated['display_name'],
            'phone' => $phone,
            'primary_location_id' => $validated['primary_location_id'],
            'legal_name' => $validated['legal_name'] ?? null,
            'legal_form' => $validated['legal_form'] ?? null,
            'legal_form_other' => $validated['legal_form_other'] ?? null,
            'onboarding_step' => OnboardingStep::Completed->value,
            'onboarding_completed_at' => now(),
        ])->save();

        $request->user()->completeProfileOnboarding();

        // Ensure user has at least one workspace for dashboard (EnsureWorkspaceAccess requires it)
        $workspaceService = app(WorkspaceService::class);
        if ($user->workspaces()->count() === 0) {
            $workspaceName = $isBusiness
                ? ($validated['legal_name'] ?? $validated['display_name'])
                : $validated['display_name'];
            $workspaceName = trim((string) $workspaceName) !== '' ? trim((string) $workspaceName) : $user->name;

            $workspaceService->create($user, ['name' => $workspaceName], false);
        }

        // Ensure current workspace is set
        if (! $user->current_workspace_id) {
            $firstWorkspace = $user->workspaces()->first();
            if ($firstWorkspace) {
                $user->switchWorkspace($firstWorkspace);
            }
        }

        $this->logStep($request->user()->id, OnboardingStep::Details->value, 'completed');

        return redirect()->route('dashboard')->with('success', 'Welcome to NumanFreight — your profile is ready.');
    }

    /**
     * Track an onboarding step event from the frontend.
     */
    public function trackStep(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'string', 'in:welcome,workspace,plan,capabilities,account_type,details'],
            'action' => ['required', 'string', 'in:viewed,completed'],
        ]);

        $this->logStep(
            $request->user()->id,
            $validated['step'],
            $validated['action'],
        );

        return back();
    }

    /**
     * Legacy: Process the final onboarding payload and instantiate the user environment.
     * Kept for BC with old wizard.tsx (workspace flow). New flow uses storeCapabilities etc.
     */
    public function store(Request $request, WorkspaceService $workspaceService): RedirectResponse
    {
        // If already onboarded via new profile flow, shortcut
        if ($request->user() && $request->user()->isProfileOnboarded()) {
            return redirect()->route('dashboard');
        }

        // Ignore if already onboarded via legacy onboarded_at
        if ($request->user() && $request->user()->onboarded_at) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'workspace_name' => ['required', 'string', 'max:255'],
            'onboarding_plan' => ['nullable', 'string', 'in:free,pro,business'],
            'onboarding_billing_period' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $user = $request->user();

        // 1. Create the initial workspace cleanly
        $workspace = $workspaceService->create($user, [
            'name' => $validated['workspace_name'],
            'personal_workspace' => false, // Formal SaaS workspace
        ]);

        // 2. Mark the account formally onboarded
        $user->completeOnboarding();

        // Also mark public profile as completed if exists
        $profile = $user->profile()->first();
        if ($profile) {
            $profile->forceFill([
                'onboarding_step' => OnboardingStep::Completed->value,
                'onboarding_completed_at' => now(),
            ])->save();
        }

        // 3. Log onboarding completion
        $this->logStep($user->id, 'plan', 'completed');

        $selectedPlan = $validated['onboarding_plan'] ?? 'free';
        $selectedBillingPeriod = $validated['onboarding_billing_period'] ?? null;

        if ($selectedPlan !== 'free' && $selectedBillingPeriod) {
            return redirect()->route('billing.plans', [
                'onboarding' => '1',
                'recommended_plan' => $selectedPlan,
                'recommended_billing_period' => $selectedBillingPeriod,
            ])->with('success', 'Welcome! Your workspace is ready.')
                ->with('info', 'Choose your billing plan to unlock premium features.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Welcome! Your workspace is ready.');
    }

    /**
     * Log an onboarding step event if not already logged.
     */
    private function logStep(int $userId, string $step, string $action): void
    {
        OnboardingStepLog::firstOrCreate([
            'user_id' => $userId,
            'step' => $step,
            'action' => $action,
        ]);
    }
}
