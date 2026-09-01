<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MarketplaceConnection extends Model
{
    /** @use HasFactory<\Database\Factories\MarketplaceConnectionFactory> */
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
     * @return array<string, string>|null
     */
    public function contactDetailsFor(User $user): ?array
    {
        if ($user->id === $this->transporter_user_id) {
            $consignor = $this->consignor()->first();
            $profile = $consignor?->profile()->first();
            if (! $consignor || ! $profile) {
                return null;
            }

            return [
                'name' => $profile->display_name ?? $consignor->name,
                'phone' => $profile->phone ?? '',
                'email' => $consignor->email,
            ];
        }

        if ($user->id === $this->consignor_user_id) {
            $transporter = $this->transporter()->first();
            $tProfile = $transporter?->transporterProfile()->first();
            $profile = $transporter?->profile()->first();
            if (! $transporter) {
                return null;
            }

            // Respect transporter privacy flags if exists
            $phone = null;
            $email = null;
            $whatsapp = null;
            if ($tProfile) {
                if ($tProfile->phone_visible) {
                    $phone = $tProfile->phone;
                }
                if ($tProfile->email_visible) {
                    $email = $tProfile->email;
                }
                if ($tProfile->whatsapp_visible) {
                    $whatsapp = $tProfile->whatsapp;
                }
            } else {
                $phone = $profile?->phone;
                $email = $transporter->email;
            }

            return array_filter([
                'name' => $tProfile?->display_name ?? $profile?->display_name ?? $transporter->name,
                'phone' => $phone,
                'email' => $email,
                'whatsapp' => $whatsapp,
            ]);
        }

        return null;
    }
}
