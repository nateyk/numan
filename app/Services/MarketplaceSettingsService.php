<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarketplaceSettingsService
{
    /**
     * Every admin-editable Free setting, keyed by setting name.
     *
     * The union of the free_limits quotas and the free_settings periods. The
     * interest window is a period rather than a quota, so it lives outside
     * free_limits and is included explicitly here.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        $defaults = $this->defaults();

        if (! Schema::hasTable('marketplace_settings')) {
            return $defaults;
        }

        try {
            $rows = MarketplaceSetting::query()->pluck('value', 'key');
        } catch (Throwable) {
            return $defaults;
        }

        $settings = [];

        foreach ($defaults as $key => $default) {
            $settings[$key] = $rows->has($key) ? (int) $rows->get($key) : $default;
        }

        return $settings;
    }

    /**
     * The config fallback for every editable setting.
     *
     * @return array<string, int>
     */
    private function defaults(): array
    {
        $limits = array_map('intval', config('marketplace.free_limits', []));

        $periods = [];

        foreach ((array) config('marketplace.free_settings', []) as $key) {
            $periods[$key] = (int) config('marketplace.'.$key);
        }

        return array_merge($limits, $periods);
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
