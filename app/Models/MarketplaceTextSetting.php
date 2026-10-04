<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-editable text setting.
 *
 * Separate from MarketplaceSetting because that model's value column is an
 * integer. Nothing secret lives here -- these are the account details a payer is
 * meant to read off the screen before transferring money.
 */
class MarketplaceTextSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label',
        'description',
        'is_enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
