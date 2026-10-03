<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
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

        // Free plan — no payment needed, just assign
        if ((float) $amount === 0.0) {
            MarketplaceSubscription::where('workspace_id', $workspace->id)->whereIn('status', ['active', 'trialing'])->update(['status' => 'canceled', 'ends_at' => now()]);

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json(['success' => true, 'message' => 'Switched to Free plan.']);
            }

            return back()->with('success', 'Switched to Free plan.');
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
                'description' => Str::limit($plan->description ?? 'NumanFreight plan', 50, ''),
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

        return back()->withErrors(['plan' => $response['message'] ?? 'Unable to initialize Chapa payment.']);
    }
}
