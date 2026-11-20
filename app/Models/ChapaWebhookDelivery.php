<?php

namespace App\Models;

use App\Services\ChapaProviderDataSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable, minimized record of a distinct signed Chapa webhook payload.
 *
 * The existing ChapaWebhookEvent remains a processing-status summary keyed by
 * (tx_ref, event); this table preserves distinct refund deliveries underneath.
 */
class ChapaWebhookDelivery extends Model
{
    protected $fillable = [
        'payload_hash',
        'tx_ref',
        'event',
        'webhook_type',
        'payload',
        'signature',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    /** @param array<string, mixed> $value */
    public function setPayloadAttribute(array $value): void
    {
        $this->attributes['payload'] = json_encode(
            ChapaProviderDataSanitizer::summarize($value),
            JSON_THROW_ON_ERROR,
        );
    }
}
