<?php

namespace App\Models;

use App\Enums\FreightInterestStatus;
use Database\Factories\FreightInterestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreightInterest extends Model
{
    /** @use HasFactory<FreightInterestFactory> */
    use HasFactory;

    protected $fillable = [
        'freight_post_id',
        'user_id',
        'status',
        'message',
        'accepted_at',
        'contact_name',
        'contact_phone',
        'contact_email',
        'bank_account_name',
        'bank_name',
        'bank_account_number',
        'bank_iban',
        'bank_swift',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function freightPost(): BelongsTo
    {
        return $this->belongsTo(FreightPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(FreightInterestStatusHistory::class);
    }

    /**
     * @param  Builder<FreightInterest>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [FreightInterestStatus::Rejected->value, FreightInterestStatus::Withdrawn->value]);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, [FreightInterestStatus::Rejected->value, FreightInterestStatus::Withdrawn->value], true);
    }
}
