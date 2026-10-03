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
        return (int) (config('marketplace.free_limits.'.$key) ?? -1);
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
