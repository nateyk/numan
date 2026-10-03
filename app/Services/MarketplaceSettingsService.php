<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarketplaceSettingsService
{
    /**
     * All admin-editable Free limits, keyed by setting name.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        $defaults = config('marketplace.free_limits', []);

        if (! Schema::hasTable('marketplace_settings')) {
            return array_map('intval', $defaults);
        }

        try {
            $rows = MarketplaceSetting::query()->pluck('value', 'key');
        } catch (Throwable) {
            return array_map('intval', $defaults);
        }

        $settings = [];

        foreach ($defaults as $key => $default) {
            $settings[$key] = $rows->has($key) ? (int) $rows->get($key) : (int) $default;
        }

        return $settings;
    }

    public function get(string $key): int
    {
        return $this->all()[$key] ?? MarketplaceSetting::defaultFor($key);
    }

    /**
     * @param  array<string, int>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            MarketplaceSetting::updateOrCreate(['key' => $key], ['value' => (int) $value]);
        }
    }
}
