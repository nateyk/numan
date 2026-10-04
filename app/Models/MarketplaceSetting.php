<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public static function defaultFor(string $key): int
    {
        // The reveal window lives at the top level of the config rather than
        // inside free_limits, because it is a period rather than a quota.
        $fallback = config('marketplace.'.$key);

        if (is_int($fallback)) {
            return $fallback;
        }

        $limit = config('marketplace.free_limits.'.$key);

        return is_int($limit) ? $limit : -1;
    }

    /**
     * Read a limit, falling back to the config default when the row is
     * missing so a deleted setting can never crash an entitlement check.
     */
    public static function valueOrDefault(string $key): int
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting === null ? static::defaultFor($key) : (int) $setting->value;
    }
}
