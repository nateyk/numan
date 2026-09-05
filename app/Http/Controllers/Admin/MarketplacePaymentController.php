<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Services\ChapaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplacePaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = MarketplacePaymentTransaction::with(['workspace:id,name', 'plan:id,name,slug', 'user:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn ($t) => [
                'id' => $t->id,
                'tx_ref' => $t->tx_ref,
                'chapa_ref' => $t->chapa_ref,
                'workspace' => $t->workspace ? ['id' => $t->workspace->id, 'name' => $t->workspace->name] : null,
                'plan' => $t->plan ? ['name' => $t->plan->name] : null,
                'user' => $t->user ? ['name' => $t->user->name, 'email' => $t->user->email] : null,
                'amount_etb' => $t->amount_etb,
                'currency' => $t->currency,
                'status' => $t->status,
                'mode' => $t->mode,
                'verified_at' => $t->verified_at,
                'created_at' => $t->created_at,
            ]);

        $webhooks = ChapaWebhookEvent::latest()->take(20)->get()->map(fn ($e) => [
            'id' => $e->id,
            'event' => $e->event,
            'tx_ref' => $e->tx_ref,
            'status' => $e->status,
            'processed_at' => $e->processed_at,
            'created_at' => $e->created_at,
        ]);

        $subs = MarketplaceSubscription::with(['workspace:id,name', 'plan:id,name'])->latest()->take(20)->get()->map(fn ($s) => [
            'id' => $s->id,
            'workspace' => $s->workspace ? $s->workspace->name : null,
            'plan' => $s->plan ? $s->plan->name : null,
            'status' => $s->status,
            'billing_interval' => $s->billing_interval,
            'current_period_ends_at' => $s->current_period_ends_at,
            'chapa_tx_ref' => $s->chapa_tx_ref,
        ]);

        return Inertia::render('admin/marketplace-payments', [
            'transactions' => $transactions,
            'webhooks' => $webhooks,
            'subscriptions' => $subs,
        ]);
    }

    public function reconcile(Request $request, MarketplacePaymentTransaction $transaction, ChapaService $chapa)
    {
        $verified = $chapa->verifyTransaction($transaction->tx_ref);

        $transaction->update([
            'provider_response' => $verified,
            'chapa_ref' => $verified['data']['reference'] ?? $verified['data']['tx_ref'] ?? $transaction->chapa_ref,
        ]);

        return back()->with('success', 'Reconciled: '.($verified['status'] ?? 'unknown'));
    }
}
