<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceSubscription;
use App\Services\ChapaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChapaWebhookController extends Controller
{
    public function __construct(protected ChapaService $chapa) {}

    public function handle(Request $request)
    {
        if (! $this->chapa->validateWebhook($request)) {
            Log::warning('Chapa webhook signature invalid', ['payload' => $request->getContent()]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $request->json()->all() ?: json_decode($request->getContent(), true) ?? [];
        $event = $payload['event'] ?? $payload['type'] ?? 'unknown';
        $txRef = $payload['tx_ref'] ?? $payload['txRef'] ?? $payload['reference'] ?? $payload['data']['tx_ref'] ?? null;
        $chapaRef = $payload['reference'] ?? $payload['data']['reference'] ?? null;

        if (! $txRef) {
            Log::warning('Chapa webhook missing tx_ref', ['payload' => $payload]);

            return response()->json(['message' => 'Missing tx_ref'], 422);
        }

        // Dedup
        $existing = ChapaWebhookEvent::where('tx_ref', $txRef)->where('event', $event)->first();
        if ($existing && $existing->status === 'processed') {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $webhookEvent = ChapaWebhookEvent::updateOrCreate(
            ['tx_ref' => $txRef, 'event' => $event],
            [
                'chapa_ref' => $chapaRef,
                'payload' => $payload,
                'signature' => $request->header('x-chapa-signature') ?? $request->header('chapa-signature'),
                'status' => 'pending',
            ]
        );

        // Only process success events
        $isSuccess = str_contains((string) $event, 'success') || ($payload['status'] ?? null) === 'success';
        if (! $isSuccess) {
            $webhookEvent->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['message' => 'Event ignored'], 200);
        }

        // Server-side verification
        $verified = $this->chapa->verifyTransaction($txRef);
        $data = $verified['data'] ?? $verified;
        $verifiedSuccess = ($verified['status'] ?? null) === 'success' && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

        if (! $verifiedSuccess) {
            Log::info('Chapa webhook verify not success', ['tx_ref' => $txRef, 'verified' => $verified]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Verification failed'], 200);
        }

        $transaction = MarketplacePaymentTransaction::where('tx_ref', $txRef)->first();
        if (! $transaction) {
            Log::warning('Chapa webhook transaction not found', ['tx_ref' => $txRef]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Transaction not found'], 200);
        }

        // Amount / currency check
        $expected = (string) $transaction->amount_etb;
        $got = (string) ($data['amount'] ?? '');
        $currency = $data['currency'] ?? 'ETB';
        if ($currency !== 'ETB' || ($got !== '' && $got !== $expected && (float) $got !== (float) $expected)) {
            Log::warning('Chapa webhook amount mismatch', ['tx_ref' => $txRef, 'expected' => $expected, 'got' => $got]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Amount mismatch'], 200);
        }

        // Fulfill idempotently
        try {
            DB::transaction(function () use ($transaction, $verified, $webhookEvent, $chapaRef) {
                if ($transaction->status === 'verified' && $transaction->verified_at) {
                    // already done
                } else {
                    $transaction->update([
                        'status' => 'verified',
                        'verified_at' => now(),
                        'chapa_ref' => $chapaRef ?? $transaction->chapa_ref,
                        'provider_response' => $verified,
                    ]);

                    $workspace = $transaction->workspace;
                    $plan = $transaction->plan;
                    if ($workspace && $plan) {
                        MarketplaceSubscription::where('workspace_id', $workspace->id)->whereIn('status', ['active', 'trialing'])->update(['status' => 'canceled', 'ends_at' => now()]);

                        $billingInterval = $transaction->provider_response['meta']['billing_interval'] ?? $verified['meta']['billing_interval'] ?? 'monthly';
                        if (! in_array($billingInterval, ['weekly', 'monthly', 'yearly'])) {
                            $billingInterval = 'monthly';
                        }
                        $periodDays = match ($billingInterval) {
                            'weekly' => 7,
                            'yearly' => 365,
                            default => 30,
                        };

                        $sub = MarketplaceSubscription::create([
                            'workspace_id' => $workspace->id,
                            'plan_id' => $plan->id,
                            'user_id' => $transaction->user_id,
                            'status' => 'active',
                            'billing_interval' => $billingInterval,
                            'current_period_start' => now(),
                            'current_period_ends_at' => now()->addDays($periodDays),
                            'chapa_tx_ref' => $transaction->tx_ref,
                            'provider' => 'chapa',
                        ]);

                        MarketplacePaymentReceipt::create([
                            'transaction_id' => $transaction->id,
                            'subscription_id' => $sub->id,
                            'workspace_id' => $workspace->id,
                            'amount_etb' => $transaction->amount_etb,
                            'currency' => 'ETB',
                            'invoice_number' => 'NF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                            'issued_at' => now(),
                        ]);
                    }
                }

                $webhookEvent->update(['status' => 'processed', 'processed_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('Chapa webhook fulfillment failed', ['tx_ref' => $txRef, 'error' => $e->getMessage()]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Fulfillment error'], 500);
        }

        return response()->json(['message' => 'Processed'], 200);
    }
}
