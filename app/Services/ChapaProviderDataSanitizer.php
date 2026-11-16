<?php

namespace App\Services;

/**
 * Persist a small accounting/diagnostic summary, never the payer's identity.
 *
 * Chapa payloads can contain first/last names, email, phone, address and
 * arbitrary nested customer metadata. A strict allowlist is safer than trying
 * to keep pace with every new key Chapa might introduce.
 *
 * Full signed bodies are still verified in memory before this is called.
 */
class ChapaProviderDataSanitizer
{
    /** @var list<string> */
    private const SAFE_ROOT_KEYS = [
        'event',
        'type',
        'webhook_type',
        'status',
        'tx_ref',
        'txRef',
        'merchant_reference',
        'chapa_reference',
        'refunded_amount',
        'mode',
        'reference',
        'currency',
        'amount',
        'amount_etb',
    ];

    /** @var list<string> */
    private const SAFE_DATA_KEYS = [
        'status',
        'tx_ref',
        'reference',
        'currency',
        'amount',
        'amount_etb',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function summarize(array $payload): array
    {
        $safe = self::safeScalars($payload, self::SAFE_ROOT_KEYS);
        $data = $payload['data'] ?? null;

        if (is_array($data)) {
            $safe['data'] = self::safeScalars($data, self::SAFE_DATA_KEYS);
        }

        $safe['_schema'] = 'numan.chapa.payment-summary/1';

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowed
     * @return array<string, bool|int|float|string|null>
     */
    private static function safeScalars(array $payload, array $allowed): array
    {
        $safe = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
