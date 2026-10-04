<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitManualPaymentRequest;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ManualPaymentService;
use App\Services\ManualPaymentTokenService;
use App\Services\MarketplaceTextSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payer-facing side of manual payment.
 *
 * No row exists until submit. The instructions page issues a signed token
 * carrying the agreed terms, and the submit form validates it.
 *
 * The status page is addressed by the transaction's own id and authorised by
 * ownership. It is never reachable by knowing the NF code -- the code is printed
 * in banking apps and appears in screenshots, so treating it as a secret would be
 * false comfort.
 */
class ManualPaymentController extends Controller
{
    public function __construct(
        protected ManualPaymentService $manual,
        protected ManualPaymentTokenService $tokens,
        protected MarketplaceTextSettingsService $instructions,
    ) {}

    /**
     * Show the payment instructions for a plan and duration.
     */
    public function create(Request $request, string $plan, string $interval): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace === null) {
            return back()->withErrors(['manual_payment' => 'No active workspace.']);
        }

        Gate::authorize('manageBilling', $workspace);

        $settings = $this->instructions;

        // Nothing configured means nothing to show. Sending them to an empty
        // page, or worse a page with a half-filled account, would start a
        // transfer that cannot complete.
        if (! $settings->manualPaymentEnabled()) {
            return back()->withErrors([
                'manual_payment' => 'Manual payment is not available right now. Please use the card option.',
            ]);
        }

        try {
            $issued = $this->manual->issueInstructions($request->user(), $plan, $interval);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $methods = $this->manual->availableMethods();

        return Inertia::render('marketplace/manual-pay', [
            'token' => $issued['token'],
            'plan' => $issued['terms']['plan'],
            'billingInterval' => $issued['terms']['billing_interval'],
            'amountEtb' => (string) $issued['terms']['amount_etb'],
            'currency' => 'ETB',
            // What the payer puts in the transfer note.
            'txRef' => $issued['terms']['tx_ref'],
            'expiresInHours' => ManualPaymentTokenService::TTL_HOURS,
            'methods' => $methods,
            'instructions' => $settings->generalInstructions(),
        ]);
    }

    /**
     * Record the request.
     */
    public function store(SubmitManualPaymentRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace === null) {
            return back()->withErrors(['manual_payment' => 'No active workspace.']);
        }

        Gate::authorize('manageBilling', $workspace);

        $proof = $request->file('proof');

        try {
            $transaction = $this->manual->submit($request->user(), $request->string('token')->toString(), [
                'method' => $request->string('method')->toString(),
                'reference' => $request->string('reference')->toString(),
                'payer_phone' => $request->string('payer_phone')->toString(),
                'proof' => $proof,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput($request->except(['token', 'proof']));
        }

        return redirect()
            ->route('marketplace.manual.show', ['transaction' => $transaction->id])
            ->with('success', 'Payment request submitted. We will confirm it shortly.');
    }

    /**
     * The status page for one request.
     */
    public function show(Request $request, MarketplacePaymentTransaction $transaction): Response
    {
        $this->authorizeOwner($request, $transaction);

        return Inertia::render('marketplace/manual-pay-status', [
            'payment' => $this->present($transaction),
        ]);
    }

    /**
     * Every request this workspace has made.
     */
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        Gate::authorize('manageBilling', $workspace);

        $payments = MarketplacePaymentTransaction::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', MarketplacePaymentTransaction::PROVIDER_MANUAL)
            ->latest('id')
            ->paginate(15)
            ->through(fn (MarketplacePaymentTransaction $t): array => $this->present($t));

        return Inertia::render('marketplace/manual-pay-index', [
            'payments' => $payments->items(),
            'total' => $payments->total(),
            'links' => $payments->links(),
        ]);
    }

    /**
     * Explicit columns only.
     *
     * @return array<string, mixed>
     */
    private function present(MarketplacePaymentTransaction $t): array
    {
        return [
            'id' => $t->id,
            'tx_ref' => $t->tx_ref,
            'status' => $t->status,
            'method' => $t->manual_method,
            'reference' => $t->manual_reference,
            'plan_name' => $t->plan?->name,
            'billing_interval' => $t->billing_interval,
            'amount_etb' => (string) $t->amount_etb,
            'currency' => $t->currency,
            'submitted_at' => optional($t->manual_submitted_at)->toIso8601String(),
            'expires_at' => optional($t->manual_expires_at)->toIso8601String(),
            'reviewed_at' => optional($t->manual_reviewed_at)->toIso8601String(),
            'rejection_reason' => $t->manual_rejection_reason,
            // Never the proof path, and never the payer's phone back to a list
            // that other workspace members may see.
            'has_proof' => filled($t->manual_proof_path),
        ];
    }

    /**
     * Ownership, or superadmin. Never "you know the code".
     */
    private function authorizeOwner(Request $request, MarketplacePaymentTransaction $transaction): void
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $isOwnerOfWorkspace = $workspace !== null
            && $transaction->workspace_id === $workspace->id;

        if (! $isOwnerOfWorkspace && ! $user->is_superadmin) {
            abort(403);
        }
    }
}
