<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplaceTextSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the payment instructions a payer is shown.
 *
 * The mirror of MarketplaceSettingsService, which is integer-only. Kept
 * separate so the two type mismatches cannot be confused.
 *
 * The central rule: a method is offered only when it is enabled AND every field
 * it needs is filled in. A half-configured account is worse than no account,
 * because somebody will transfer money to it.
 */
class MarketplaceTextSettingsService
{
    /**
     * The fields each method requires before it may be shown.
     *
     * @var array<string, list<string>>
     */
    public const REQUIRED_FIELDS = [
        'telebirr' => [
            'manual_payment_telebirr_number',
            'manual_payment_telebirr_name',
        ],
        'cbe_birr' => [
            'manual_payment_cbe_birr_number',
            'manual_payment_cbe_birr_name',
        ],
        'bank_transfer' => [
            'manual_payment_bank_transfer_number',
            'manual_payment_bank_transfer_name',
            'manual_payment_bank_transfer_bank',
        ],
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'telebirr' => 'Telebirr',
        'cbe_birr' => 'CBE Birr',
        'bank_transfer' => 'Bank transfer',
    ];

    /**
     * Every setting row, keyed by key.
     *
     * @return array<string, array{value: ?string, label: string, description: ?string, is_enabled: bool}>
     */
    public function all(): array
    {
        if (! Schema::hasTable('marketplace_text_settings')) {
            return [];
        }

        try {
            $rows = MarketplaceTextSetting::query()->get();
        } catch (Throwable) {
            return [];
        }

        $settings = [];

        foreach ($rows as $row) {
            $settings[$row->key] = [
                'value' => $row->value,
                'label' => $row->label,
                'description' => $row->description,
                'is_enabled' => (bool) $row->is_enabled,
            ];
        }

        return $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key]['value'] ?? null;

        return filled($value) ? $value : $default;
    }

    /**
     * @param  array<string, array{value: ?string, label?: string, description?: string, is_enabled?: bool}>  $values
     */
    public function setMany(array $values, ?int $updatedBy = null): void
    {
        foreach ($values as $key => $value) {
            $row = MarketplaceTextSetting::query()->firstOrNew(['key' => $key]);

            if (array_key_exists('value', $value)) {
                // Blank means "not configured", not "an empty string to show".
                // An empty value also switches the row off, because leaving it
                // enabled with nothing in it is a state no consumer wants.
                $row->value = filled($value['value']) ? (string) $value['value'] : null;

                if ($row->value === null) {
                    $row->is_enabled = false;
                }
            }

            if (array_key_exists('label', $value)) {
                $row->label = $value['label'];
            }

            if (array_key_exists('description', $value)) {
                $row->description = $value['description'];
            }

            if (array_key_exists('is_enabled', $value)) {
                $row->is_enabled = (bool) $value['is_enabled'];
            }

            $row->updated_by = $updatedBy;
            $row->save();
        }
    }

    /**
     * The methods that are fully configured and enabled, with their details.
     *
     * A method missing any required field is dropped entirely rather than shown
     * partially.
     *
     * @return array<string, array{label: string, fields: array<string, string>}>
     */
    public function availableMethods(): array
    {
        $settings = $this->all();
        $methods = [];

        foreach (self::REQUIRED_FIELDS as $method => $requiredKeys) {
            $enabled = true;
            $fields = [];

            foreach ($requiredKeys as $key) {
                $setting = $settings[$key] ?? null;

                if ($setting === null || ! $setting['is_enabled'] || blank($setting['value'])) {
                    $enabled = false;
                    break;
                }

                $fields[$key] = (string) $setting['value'];
            }

            if (! $enabled) {
                continue;
            }

            $methods[$method] = [
                'label' => self::LABELS[$method] ?? $method,
                'fields' => $fields,
            ];
        }

        return $methods;
    }

    /**
     * Is manual payment offered at all?
     *
     * Drives the "Pay manually" button. With nothing configured the button stays
     * hidden, so nobody starts a transfer they cannot complete.
     */
    public function manualPaymentEnabled(): bool
    {
        return $this->availableMethods() !== [];
    }

    /**
     * A method a payer may choose.
     */
    public function methodIsAvailable(string $method): bool
    {
        return array_key_exists($method, $this->availableMethods());
    }

    public function generalInstructions(): ?string
    {
        $setting = $this->all()['manual_payment_instructions'] ?? null;

        if ($setting === null || ! $setting['is_enabled']) {
            return null;
        }

        return $setting['value'];
    }

    /**
     * Every method key, for validation and the admin form.
     *
     * @return list<string>
     */
    public static function knownMethods(): array
    {
        return MarketplacePaymentTransaction::MANUAL_METHODS;
    }
}
