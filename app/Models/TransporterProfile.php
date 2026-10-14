<?php

namespace App\Models;

use App\Enums\TransporterStatus;
use App\Enums\VerificationLevel;
use Database\Factories\TransporterProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TransporterProfile extends Model
{
    /** @use HasFactory<TransporterProfileFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slug',
        'display_name',
        'legal_name',
        'phone',
        'whatsapp',
        'email',
        'business_description',
        'is_company',
        'status',
        'verification_level',
        'completion_score',
        'phone_visible',
        'whatsapp_visible',
        'email_visible',
        'primary_location_id',
    ];

    protected function casts(): array
    {
        return [
            'is_company' => 'boolean',
            'phone_visible' => 'boolean',
            'whatsapp_visible' => 'boolean',
            'email_visible' => 'boolean',
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
        return $this->belongsToMany(TruckType::class, 'transporter_profile_truck_type')
            ->withTimestamps();
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransporterRoute::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TransporterDocument::class);
    }

    public function moderations(): HasMany
    {
        return $this->hasMany(TransporterProfileModeration::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransporterProfileRevision::class);
    }

    public function isApproved(): bool
    {
        return $this->status === TransporterStatus::Approved->value;
    }

    public function isPending(): bool
    {
        return $this->status === TransporterStatus::Pending->value;
    }

    public function isVerified(): bool
    {
        return $this->verification_level === VerificationLevel::Verified->value;
    }

    /**
     * @param  Builder<TransporterProfile>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TransporterStatus::Approved->value);
    }

    /**
     * @param  Builder<TransporterProfile>  $query
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', TransporterStatus::Approved->value);
    }

    /**
     * @param  Builder<TransporterProfile>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransporterStatus::Pending->value);
    }

    /**
     * An explicit allowlist, never logFillable().
     *
     * logFillable() wrote the value of every fillable attribute into
     * activity_log.properties, which put transporter phone, whatsapp, email and
     * legal_name into the audit trail in plaintext.
     *
     * Those four are absent from the list rather than redacted, because Spatie
     * logs the value of whatever it is asked to log. The *_visible flags stay:
     * knowing a transporter published or hid their number is audit-relevant and
     * carries no contact data.
     */
    /**
     * Fields a transporter needs before they can usefully express interest.
     *
     * Deliberately the three that make them contactable and locatable, and
     * nothing more. A legal name, a business description and uploaded documents
     * are things a *good* profile has, but gating on them means a perfectly good
     * lorry driver waits for paperwork before saying they can take a load.
     *
     * @var list<string>
     */
    public const REQUIRED_FIELDS = [
        'display_name',
        'phone',
        'primary_location_id',
    ];

    /**
     * Which required fields are still empty.
     *
     * Empty means null or blank. A field holding whitespace is as absent as one
     * holding null, and treating " " as complete is how a profile reads as
     * finished while showing a blank line to the counterparty.
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        return array_values(array_filter(
            self::REQUIRED_FIELDS,
            fn (string $field): bool => $this->{$field} === null || trim((string) $this->{$field}) === '',
        ));
    }

    /**
     * Whether this profile carries enough to act as a transporter.
     *
     * This replaced `status === 'approved'` as the thing deciding whether
     * somebody may express interest. Approval is an admin's judgement about
     * quality — a badge and a ranking signal now, not a wall. Gating on it meant
     * nobody who was not already trusted could take their first step, and since
     * only AdminTransporterController can set it, nothing ever moved a profile
     * out of pending.
     *
     * Protection against contact details being harvested is the metered reveal
     * ledger, which logs every single use. A wall in front of expressing interest
     * added no protection; it only stopped the market from working.
     */
    public function isComplete(): bool
    {
        return $this->missingFields() === [];
    }

    /**
     * Give every profile a slug, whatever created it.
     *
     * The column is NOT NULL and unique, but the logic lived in
     * TransporterProfileController::generateSlug — so any other code path that
     * created a profile hit a database integrity violation. Creating a stub when
     * somebody picks up the carrier capability is the first such path; the next
     * one would have been too.
     *
     * An explicitly supplied slug wins, so the controller keeps working unchanged.
     */
    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (filled($profile->slug)) {
                return;
            }

            $base = Str::slug($profile->display_name ?: 'carrier');
            $slug = $base.'-'.Str::random(4);
            $i = 1;

            while (self::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.Str::random(4).$i++;
            }

            $profile->slug = $slug;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'slug',
                'display_name',
                'business_description',
                'is_company',
                'status',
                'verification_level',
                'completion_score',
                'phone_visible',
                'whatsapp_visible',
                'email_visible',
                'primary_location_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('transporter_profile');
    }
}
