<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplacePaymentReview extends Model
{
    public const REASONS = [
        'paid_after_cancellation',
        'duplicate_charge',
        'refund_request',
        'chargeback_dispute',
        'verification_mismatch',
    ];

    public const TRANSITIONS = [
        'open' => ['investigating'],
        'investigating' => ['submitted_to_chapa', 'closed_no_refund'],
        'submitted_to_chapa' => ['provider_reported_refunded', 'investigating', 'closed_no_refund'],
    ];

    protected $fillable = [
        'transaction_id',
        'reason',
        'status',
        'opened_by',
        'reviewed_by',
        'provider_reference',
        'provider_reviewed_at',
    ];

    protected function casts(): array
    {
        return ['provider_reviewed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MarketplacePaymentTransaction::class, 'transaction_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplacePaymentReviewEvent::class, 'review_id');
    }
}
