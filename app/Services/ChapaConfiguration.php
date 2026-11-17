<?php

namespace App\Services;

use App\Models\ChapaPaymentSetting;
use Illuminate\Support\Facades\Schema;

class ChapaConfiguration
{
    private const FIELDS = [
        'mode' => 'mode',
        'publicKey' => 'public_key',
        'secretKey' => 'secret_key',
        'webhookSecret' => 'webhook_secret',
    ];

    /**
     * Admin overrides take precedence over the existing .env configuration.
     * Never store the decrypted result in a shared cache or a log.
     *
     * The table check lets migration and first-install commands run before
     * the new settings migration has been applied.
     */
    public function value(string $key): ?string
    {
        if (! array_key_exists($key, self::FIELDS)) {
            return null;
        }

        if (Schema::hasTable('chapa_payment_settings')) {
            $setting = ChapaPaymentSetting::query()->find(1);
            $saved = $setting?->{self::FIELDS[$key]};

            if (is_string($saved) && trim($saved) !== '') {
                return $saved;
            }
        }

        $fallback = config('chapa.'.$key);

        return is_string($fallback) && trim($fallback) !== '' ? $fallback : null;
    }

    public function mode(): string
    {
        return $this->value('mode') === 'live' ? 'live' : 'test';
    }

    public function configured(string $key): bool
    {
        return filled($this->value($key));
    }
}
