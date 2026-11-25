<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChapaPaymentSetting extends Model
{
    protected $fillable = [
        'mode',
        'public_key',
        'secret_key',
        'webhook_secret',
        'updated_by',
    ];

    // Never serialize the keys, including the public key, to a dashboard
    // Inertia prop or an accidental JSON response.
    protected $hidden = ['public_key', 'secret_key', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'public_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }
}
