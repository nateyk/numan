<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporterProfileRevision extends Model
{
    protected $fillable = [
        'transporter_profile_id',
        'payload',
        'status',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TransporterProfile::class, 'transporter_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
