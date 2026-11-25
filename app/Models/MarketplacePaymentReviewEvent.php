<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePaymentReviewEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'actor_id',
        'from_status',
        'to_status',
        'provider_reference',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(MarketplacePaymentReview::class, 'review_id');
    }
}
