<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChapaWebhookEvent extends Model
{
    protected $fillable = [
        'event',
        'tx_ref',
        'chapa_ref',
        'payload',
        'signature',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
