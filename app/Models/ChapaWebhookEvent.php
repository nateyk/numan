<?php

namespace App\Models;

use App\Services\ChapaProviderDataSanitizer;
use Illuminate\Database\Eloquent\Model;

class ChapaWebhookEvent extends Model
{
    protected $fillable = [
        'event',
        'tx_ref',
        'chapa_ref',
        'payload',
        'signature',
        'payload_hash',
        'status',
        'processed_at',
    ];

    /**
     * Store only an allowlisted summary of the verified webhook. The incoming
     * raw body remains available to HMAC verification and SHA-256 hashing
     * before this mutator runs, but is never persisted wholesale.
     *
     * @param  array<string, mixed>  $value
     */
    public function setPayloadAttribute(array $value): void
    {
        $this->attributes['payload'] = json_encode(
            ChapaProviderDataSanitizer::summarize($value),
            JSON_THROW_ON_ERROR,
        );
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
