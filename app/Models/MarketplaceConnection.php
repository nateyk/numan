<?php

namespace App\Models;

use App\Services\ContactDetailsResolver;
use Database\Factories\MarketplaceConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MarketplaceConnection extends Model
{
    /** @use HasFactory<MarketplaceConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id',
        'freight_post_id',
        'freight_interest_id',
        'consignor_user_id',
        'transporter_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function freightPost(): BelongsTo
    {
        return $this->belongsTo(FreightPost::class);
    }

    public function freightInterest(): BelongsTo
    {
        return $this->belongsTo(FreightInterest::class);
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consignor_user_id');
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transporter_user_id');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(MarketplaceConnectionOutcome::class);
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(MarketplaceConnectionOutcome::class)->latestOfMany();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MarketplaceConnectionAuditLog::class);
    }

    /**
     * The counterparty's contact details, for a participant in the connection.
     *
     * Resolution is delegated to ContactDetailsResolver, which reads the single
     * public profile and honours its visibility flags. This method used to have
     * its own transporter branch and a fallback that ignored those flags, so a
     * number could be released on the connection page that the reveal endpoint
     * would withhold.
     *
     * @return array<string, string>|null
     */
    public function contactDetailsFor(User $user): ?array
    {
        $counterparty = match (true) {
            $user->id === $this->transporter_user_id => $this->consignor()->first(),
            $user->id === $this->consignor_user_id => $this->transporter()->first(),
            default => null,
        };

        if ($counterparty === null) {
            return null;
        }

        return array_filter(
            app(ContactDetailsResolver::class)->resolve($counterparty),
            fn (?string $value): bool => filled($value),
        );
    }
}
