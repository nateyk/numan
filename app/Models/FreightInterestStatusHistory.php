<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightInterestStatusHistory extends Model
{
    protected $fillable = [
        'freight_interest_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
    ];

    public function freightInterest(): BelongsTo
    {
        return $this->belongsTo(FreightInterest::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
