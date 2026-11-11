<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reveal attempt refused because the quota was used up.
 *
 * Analytics only. It records that somebody hit the wall and when, which is what
 * makes "did the paywall convert" answerable. It never grants access.
 */
class ContactRevealBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'revealed_id',
        'freight_post_id',
        'interest_id',
        'connection_id',
        'limit_at_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revealed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_id');
    }
}
