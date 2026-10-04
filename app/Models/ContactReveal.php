<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user has looked at another user's contact details.
 *
 * The row is permanent and its existence is what makes a re-reveal free: a
 * repeated reveal of the same person is idempotent and costs no quota, however
 * long ago the first one happened.
 */
class ContactReveal extends Model
{
    use HasFactory;

    protected $fillable = [
        'revealer_id',
        'revealed_id',
        'freight_post_id',
        'interest_id',
        'connection_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function revealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealer_id');
    }

    public function revealed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_id');
    }

    public function freightPost(): BelongsTo
    {
        return $this->belongsTo(FreightPost::class);
    }

    public function interest(): BelongsTo
    {
        return $this->belongsTo(FreightInterest::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceConnection::class);
    }

    /**
     * Has this revealer already looked at this user's contact details, ever?
     *
     * Used by page loads to decide whether to include the contact without
     * asking again.
     */
    public static function hasPair(int $revealerId, int $revealedId): bool
    {
        return static::query()
            ->where('revealer_id', $revealerId)
            ->where('revealed_id', $revealedId)
            ->exists();
    }
}
