<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePaymentReceipt extends Model
{
    protected $fillable = [
        'transaction_id',
        'subscription_id',
        'workspace_id',
        'amount_etb',
        'currency',
        'invoice_number',
        'pdf_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MarketplacePaymentTransaction::class, 'transaction_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSubscription::class, 'subscription_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
