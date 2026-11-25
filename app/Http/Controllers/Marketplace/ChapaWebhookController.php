<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ChapaWebhookDelivery;
use App\Models\ChapaWebhookEvent;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaService;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChapaWebhookController extends Controller
{
    public function __construct(
        protected ChapaService $chapa,
        protected MarketplaceFulfillmentService $fulfillment,
    ) {}

    public function handle(Request $request)
    {
        if (! $this->chapa->validateWebhook($request)) {
            // The body is deliberately not logged. A Chapa webhook carries the
            // payer's first name, last name, email and phone number, and this
            // branch is reachable by anyone who can send a request. The byte
            // count is enough to tell an empty body from a probe.
            Log::warning('Chapa webhook signature invalid', [
                // The declared length, never the body. Enough to tell an empty
                // request from a probe.
                'bytes' => $request->server('CONTENT_LENGTH'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $request->json()->all() ?: json_decode($request->getContent(), true) ?? [];
        $event = $payload['event'] ?? $payload['type'] ?? 'unknown';
        $webhookType = $payload['webhook_type'] ?? 'payment';
        $txRef = $payload['tx_ref'] ?? $payload['txRef']
            ?? $payload['merchant_reference'] ?? $payload['reference']
            ?? $payload['data']['tx_ref'] ?? null;
        $chapaRef = $payload['reference'] ?? $payload['data']['reference'] ?? null;

        if (! $txRef) {
            // The event name is from a small fixed vocabulary; the rest of the
            // payload is the payer's contact details and stays out.
            Log::warning('Chapa webhook missing tx_ref', ['event' => $event]);

            return response()->json(['message' => 'Missing tx_ref'], 422);
        }

        // The old processing row is intentionally one per (tx_ref, event).
        // Multiple partial refunds legitimately share both fields, so record
        // each DISTINCT authenticated delivery before its summary is deduped.
        // Persist only allowlisted accounting fields; no payer identity.
        $bodyHash = hash('sha256', $request->getContent());
        ChapaWebhookDelivery::firstOrCreate(
            ['payload_hash' => $bodyHash],
            [
                'tx_ref' => $txRef,
                'event' => $event,
                'webhook_type' => $webhookType,
                'payload' => $payload,
                'signature' => $request->header('x-chapa-signature') ?? $request->header('chapa-signature'),
                'received_at' => now(),
            ],
        );

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
                // Digest the exact signed bytes, not JSON re-encoded by Laravel.
                'payload_hash' => $bodyHash,
                'status' => 'pending',
            ]
        );

        // Never use an arbitrary "success" substring or the payload's
        // top-level status alone to identify a new charge. Chapa v1 emits
        // charge.success; v2 payment.success is included for event routing,
        // but must still pass our v1 provider verification before fulfillment.
        // Refund events may share the payment.* namespace and payout/refund
        // notifications may also say status=success.
        $isSuccess = $webhookType === 'payment'
            && in_array($event, ['charge.success', 'payment.success'], true)
            && ($payload['status'] ?? null) === 'success';
        if (! $isSuccess) {
            $webhookEvent->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['message' => 'Event ignored'], 200);
        }

        try {
            $verified = $this->chapa->verifyTransaction($txRef);
        } catch (ConnectionException|RequestException $e) {
            // Ask the provider to retry. The transaction remains unfulfilled,
            // and our scheduled reconciliation will verify it separately.
            report($e);
            $webhookEvent->update(['status' => 'pending']);

            return response()->json(['message' => 'Temporary verification failure'], 503);
        }
        $data = $verified['data'] ?? $verified;
        $verifiedSuccess = ($verified['status'] ?? null) === 'success' && (($data['status'] ?? null) === 'success' || ($data['status'] ?? null) === 'paid');

        if (! $verifiedSuccess) {
            // Only the status fields. The verify response also returns the
            // payer's name, email and phone number, which have no business in a
            // log file that outlives the request.
            Log::info('Chapa webhook verify not success', [
                'tx_ref' => $txRef,
                'status' => $verified['status'] ?? null,
                'payment_status' => $data['status'] ?? null,
            ]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Verification failed'], 200);
        }

        $transaction = MarketplacePaymentTransaction::where('tx_ref', $txRef)->first();
        if (! $transaction) {
            Log::warning('Chapa webhook transaction not found', ['tx_ref' => $txRef]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Transaction not found'], 200);
        }

        if (! $this->chapa->verifiedPaymentMatches($verified, $transaction)) {
            Log::warning('Chapa webhook verified payment details incomplete or mismatched', [
                'tx_ref' => $txRef,
            ]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Payment verification mismatch'], 200);
        }

        try {
            $this->fulfillment->fulfill($transaction, $verified);
            $webhookEvent->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Chapa webhook fulfillment failed', ['tx_ref' => $txRef, 'error' => $e->getMessage()]);
            $webhookEvent->update(['status' => 'failed']);

            return response()->json(['message' => 'Fulfillment error'], 500);
        }

        return response()->json(['message' => 'Processed'], 200);
    }
}
