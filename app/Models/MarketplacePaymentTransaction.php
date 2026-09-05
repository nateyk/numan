<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePaymentTransaction extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'user_id',
        'tx_ref',
        'chapa_ref',
        'amount_etb',
        'currency',
        'status',
        'mode',
        'provider_response',
        'verified_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
            'provider_response' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketplacePlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->verified_at !== null;
    }
}
