<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Services\ChapaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(protected ChapaService $chapa) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:marketplace_plans,slug'],
            'billing_interval' => ['required', 'in:weekly,monthly,yearly'],
        ]);

        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return back()->withErrors(['plan' => 'No active workspace.']);
        }

        $plan = MarketplacePlan::where('slug', $data['plan'])->where('is_active', true)->firstOrFail();

        $amount = match ($data['billing_interval']) {
            'weekly' => $plan->price_weekly_etb,
            'monthly' => $plan->price_monthly_etb,
            'yearly' => $plan->price_yearly_etb,
        };
        $amountStr = (string) $amount;

        // Checkout is for paid durations only. The Free state is implicit
        // (no subscription), so a zero-priced plan can never be "bought" and
        // must never cancel a running paid subscription.
        if ((float) $amount <= 0.0) {
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'error' => 'This duration has no price. Please choose Weekly, Monthly or Yearly.',
                ], 422);
            }

            return back()->withErrors(['plan' => 'This duration has no price. Please choose Weekly, Monthly or Yearly.']);
        }

        $txRef = $this->chapa->generateReference('NF-'.$plan->slug.'-'.$data['billing_interval']);

        $transaction = MarketplacePaymentTransaction::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'tx_ref' => $txRef,
            'amount_etb' => $amount,
            'currency' => 'ETB',
            'status' => 'pending',
            'mode' => config('chapa.mode', 'test'),
            'billing_interval' => $data['billing_interval'],
        ]);

        $callback = route('chapa.callback', ['tx_ref' => $txRef]);

        // Chapa only accepts letters, numbers, hyphens, underscores, spaces
        // and dots in the customization description. Plan copy contains
        // commas and dashes, so strip anything outside that set (falling
        // back to the plan name) rather than letting Chapa 422 the payment.
        $description = (string) ($plan->description ?? $plan->name);
        $description = trim((string) preg_replace('/[^A-Za-z0-9 _.\-]+/', '', $description));
        $description = (string) preg_replace('/\s+/', ' ', $description);

        if ($description === '') {
            $description = (string) $plan->name;
        }

        $payload = [
            'amount' => $amountStr,
            'currency' => 'ETB',
            'email' => $user->email,
            'first_name' => explode(' ', $user->name)[0] ?? $user->name,
            'last_name' => trim(Str::after($user->name, explode(' ', $user->name)[0] ?? '')) ?: 'User',
            'tx_ref' => $txRef,
            'callback_url' => $callback,
            'return_url' => $callback,
            'customization' => [
                'title' => Str::limit($plan->name, 16, ''),
                'description' => Str::limit($description, 50, ''),
            ],
            'meta' => [
                'plan_slug' => $plan->slug,
                'workspace_id' => $workspace->id,
                'billing_interval' => $data['billing_interval'],
            ],
        ];

        $response = $this->chapa->initializePayment($payload);

        $transaction->update([
            'provider_response' => $response,
            'status' => 'initialized',
            'chapa_ref' => $response['data']['tx_ref'] ?? $response['data']['reference'] ?? null,
        ]);

        if (($response['status'] ?? null) === 'success' && isset($response['data']['checkout_url'])) {
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json(['checkout_url' => $response['data']['checkout_url']]);
            }

            return redirect()->away($response['data']['checkout_url']);
        }

        $message = $response['message'] ?? 'Unable to initialize Chapa payment.';

        // Chapa field errors arrive as an object ({field: [messages]}),
        // which the frontend cannot render. Flatten to a readable string.
        if (is_array($message)) {
            $message = collect($message)->flatten()->implode(' ');
        }

        if (! is_string($message) || $message === '') {
            $message = 'Unable to initialize Chapa payment.';
        }

        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json([
                'success' => false,
                'error' => $message,
            ], 422);
        }

        return back()->withErrors(['plan' => $message]);
    }
}
