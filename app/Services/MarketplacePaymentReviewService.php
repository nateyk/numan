<?php

namespace App\Services;

use App\Models\MarketplacePaymentReview;
use App\Models\MarketplacePaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplacePaymentReviewService
{
    public function open(MarketplacePaymentTransaction $transaction, User $admin, string $reason): MarketplacePaymentReview
    {
        return DB::transaction(function () use ($transaction, $admin, $reason): MarketplacePaymentReview {
            $locked = MarketplacePaymentTransaction::whereKey($transaction->id)
                ->lockForUpdate()->firstOrFail();

            if ($locked->provider !== MarketplacePaymentTransaction::PROVIDER_CHAPA) {
                throw ValidationException::withMessages(['reason' => 'Only Chapa charges belong in this review queue.']);
            }

            if ((int) $locked->user_id === (int) $admin->id) {
                throw ValidationException::withMessages(['reason' => 'You cannot review a payment belonging to yourself.']);
            }

            if (! in_array($reason, MarketplacePaymentReview::REASONS, true)) {
                throw ValidationException::withMessages(['reason' => 'Unknown review reason.']);
            }

            $existing = MarketplacePaymentReview::where('transaction_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            $review = MarketplacePaymentReview::create([
                'transaction_id' => $locked->id,
                'reason' => $reason,
                'status' => 'open',
                'opened_by' => $admin->id,
            ]);

            $review->events()->create([
                'actor_id' => $admin->id,
                'from_status' => null,
                'to_status' => 'open',
                'created_at' => now(),
            ]);

            return $review;
        });
    }

    public function transition(
        MarketplacePaymentReview $review,
        User $admin,
        string $to,
        ?string $providerReference = null,
    ): MarketplacePaymentReview {
        return DB::transaction(function () use ($review, $admin, $to, $providerReference): MarketplacePaymentReview {
            $locked = MarketplacePaymentReview::whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->transaction->user_id === (int) $admin->id) {
                throw ValidationException::withMessages(['status' => 'You cannot resolve your own payment.']);
            }

            if ($locked->status === $to) {
                return $locked;
            }

            $allowed = MarketplacePaymentReview::TRANSITIONS[$locked->status] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Invalid review transition.']);
            }

            if ($to === 'provider_reported_refunded' && blank($providerReference)) {
                throw ValidationException::withMessages([
                    'provider_reference' => 'Enter the Chapa refund reference after confirming its outcome in the provider dashboard.',
                ]);
            }

            $old = $locked->status;
            $reference = $providerReference === null ? null : trim($providerReference);

            $locked->update([
                'status' => $to,
                'reviewed_by' => $admin->id,
                'provider_reference' => $reference ?: $locked->provider_reference,
                // Manual reviewer attestation, NOT an API-verified refund result.
                'provider_reviewed_at' => $to === 'provider_reported_refunded' ? now() : $locked->provider_reviewed_at,
            ]);

            $locked->events()->create([
                'actor_id' => $admin->id,
                'from_status' => $old,
                'to_status' => $to,
                'provider_reference' => $reference,
                'created_at' => now(),
            ]);

            return $locked;
        });
    }
}
