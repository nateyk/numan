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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('transporter_profile');
    }
}
