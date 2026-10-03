<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSubscription extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'user_id',
        'status',
        'billing_interval',
        'current_period_start',
        'current_period_ends_at',
        'trial_ends_at',
        'ends_at',
        'quantity',
        'chapa_tx_ref',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * withTrashed is a safety net: retired plans are archived with
     * is_active = false, but if one is ever soft deleted, existing
     * subscribers must still resolve their limits rather than lose access.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketplacePlan::class, 'plan_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']) && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function onGracePeriod(): bool
    {
        return $this->status === 'canceled' && $this->ends_at && $this->ends_at->isFuture();
    }
}
