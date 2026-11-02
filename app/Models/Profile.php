<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use App\Enums\VerificationStatus;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Public marketplace profile — the single profile for both consignors and
 * transporters.
 *
 * One row per user. The table is `public_profiles` (explicit) but the model is
 * `Profile` for ergonomic `user->profile`.
 */
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'public_profiles';

    protected $fillable = [
        'user_id',
        'slug',
        'posts_freight',
        'provides_transport',
        'account_type',
        'display_name',
        'phone',
        'whatsapp',
        'phone_numbers',
        'whatsapp_numbers',
        'email',
        'business_description',
        'primary_location_id',
        // Contact visibility lives here rather than on transporter_profiles so
        // that a consignor can refuse publication too. It used to exist only for
        // transporters, which meant a consignor's number was always released.
        'phone_visible',
        'whatsapp_visible',
        'email_visible',
        'legal_name',
        'legal_form',
        'legal_form_other',
        'status',
        'verification_status',
        'verification_note',
        'verification_requested_at',
        'verification_reviewed_at',
        'completion_score',
    ];

    protected function casts(): array
    {
        return [
            'phone_numbers' => 'array',
            'whatsapp_numbers' => 'array',
            'posts_freight' => 'boolean',
            'provides_transport' => 'boolean',
            'phone_visible' => 'boolean',
            'whatsapp_visible' => 'boolean',
            'email_visible' => 'boolean',
            'verification_requested_at' => 'datetime',
            'verification_reviewed_at' => 'datetime',
            'completion_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function truckTypes(): BelongsToMany
    {
        return $this->belongsToMany(TruckType::class, 'profile_truck_type')
            ->withTimestamps();
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ProfileRoute::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProfileDocument::class);
    }

    public function moderations(): HasMany
    {
        return $this->hasMany(ProfileModeration::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified->value;
    }

    public function isApproved(): bool
    {
        return $this->status === ProfileStatus::Approved->value;
    }

    public function isPending(): bool
    {
        return $this->status === ProfileStatus::Pending->value;
    }

    public function hasCapabilities(): bool
    {
        return (bool) ($this->posts_freight || $this->provides_transport);
    }

    /**
     * A slug that is free on public_profiles.
     *
     * Seeds from the supplied value, then the display name, then the account
     * name. A random suffix keeps two businesses with the same name apart and
     * means a name change never has to wait for a free slug.
     */
    public function generateUniqueSlug(?string $seed = null): string
    {
        $seed = filled($seed)
            ? $seed
            : ($this->display_name ?: ($this->user?->name ?: 'profile'));

        $base = Str::slug($seed) ?: 'profile';
        $slug = $base.'-'.Str::random(4);
        $i = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::random(4).$i++;
        }

        return $slug;
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (filled($profile->slug)) {
                return;
            }

            $profile->slug = $profile->generateUniqueSlug();
        });
    }

    /**
     * An explicit allowlist, never logFillable().
     *
     * logFillable() writes the *values* of every fillable attribute into
     * activity_log.properties, which put every user's phone number and legal
     * name into the audit trail in plaintext, permanently and exportable.
     *
     * phone, WhatsApp, email and legal_name are therefore absent from this list
     * rather than redacted: Spatie logs the value of whatever it is told to log,
     * so the only way to keep contact data out of the log is not to ask for it.
     * The log still records that the profile was updated — just without the
     * sensitive payload.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'slug',
                'posts_freight',
                'provides_transport',
                'account_type',
                'display_name',
                'business_description',
                'primary_location_id',
                'legal_form',
                'status',
                'verification_status',
                'completion_score',
                'phone_visible',
                'whatsapp_visible',
                'email_visible',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('profile');
    }
}
