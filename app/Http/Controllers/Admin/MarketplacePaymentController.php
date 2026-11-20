<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentReview;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Services\ChapaSubscriptionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplacePaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = MarketplacePaymentTransaction::with(['workspace:id,name', 'plan:id,name,slug', 'user:id,name,email', 'paymentReview:id,transaction_id,reason,status'])
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
                'provider' => $t->provider,
                'review' => $t->paymentReview ? [
                    'id' => $t->paymentReview->id,
                    'reason' => $t->paymentReview->reason,
                    'status' => $t->paymentReview->status,
                ] : null,
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

        $reviews = MarketplacePaymentReview::query()
            ->with(['transaction:id,tx_ref,amount_etb,currency'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn ($review) => [
                'id' => $review->id,
                'reason' => $review->reason,
                'status' => $review->status,
                'provider_reference' => $review->provider_reference,
                'created_at' => $review->created_at,
                'tx_ref' => $review->transaction?->tx_ref,
                'amount_etb' => $review->transaction?->amount_etb,
                'currency' => $review->transaction?->currency,
                'history_count' => $review->events()->count(),
            ]);

        return Inertia::render('admin/marketplace-payments', [
            'transactions' => $transactions,
            'webhooks' => $webhooks,
            'subscriptions' => $subs,
            'reviews' => $reviews,
        ]);
    }

    public function reconcile(MarketplacePaymentTransaction $transaction, ChapaSubscriptionVerifier $verifier)
    {
        // The admin list also includes manual transfers. Only the manual
        // approval flow may confirm those; never send their reference to Chapa.
        if (! $transaction->isChapa()) {
            return back()->withErrors(['payment' => 'This is not a Chapa transaction. Review manual payments separately.']);
        }

        if (! in_array($transaction->status, ['pending', 'initialized'], true)) {
            return back()->withErrors([
                'payment' => 'This payment is not awaiting Chapa settlement. Open a review case for a canceled, failed or already verified transaction.',
            ]);
        }

        try {
            $status = $verifier->verify($transaction);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return back()->withErrors(['payment' => 'Chapa verification is temporarily unavailable. Keep the original reference and retry later.']);
        }

        return match ($status) {
            'verified' => back()->with('success', 'Chapa payment verified, subscription and receipt recorded.'),
            'failed' => back()->withErrors(['payment' => 'Chapa confirmed the payment failed. No subscription was granted.']),
            'mismatch' => back()->withErrors(['payment' => 'Provider amount, currency, mode or receipt is inconsistent. Review before another checkout.']),
            'canceled' => back()->withErrors(['payment' => 'Checkout was canceled while verification was pending. Review any late charge without granting access.']),
            default => back()->with('info', 'Payment is still pending at Chapa. No entitlement was changed.'),
        };
    }
}
