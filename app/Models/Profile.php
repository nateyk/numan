<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Public marketplace profile — modern replacement for legacy marketplace_profiles.
 *
 * One row per user, stores the 3-step onboarding state:
 * capabilities → account_type → details → completed
 *
 * Table is `public_profiles` (explicit) but model is `Profile` for ergonomic `user->profile`.
 */
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'public_profiles';

    protected $fillable = [
        'user_id',
        'posts_freight',
        'provides_transport',
        'account_type',
        'display_name',
        'phone',
        'primary_location_id',
        'legal_name',
        'legal_form',
        'legal_form_other',
        'onboarding_step',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'posts_freight' => 'boolean',
            'provides_transport' => 'boolean',
            'onboarding_completed_at' => 'datetime',
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

    public function isOnboarded(): bool
    {
        return $this->onboarding_step === 'completed' && $this->onboarding_completed_at !== null;
    }

    public function onboardingStep(): string
    {
        if ($this->isOnboarded()) {
            return 'completed';
        }

        return $this->onboarding_step ?? 'capabilities';
    }

    /**
     * @return 'capabilities'|'account_type'|'details'
     */
    public function currentStep(): string
    {
        $step = $this->onboardingStep();

        if ($step === 'completed') {
            return 'details';
        }

        if (! in_array($step, ['capabilities', 'account_type', 'details'], true)) {
            return 'capabilities';
        }

        return $step;
    }

    public function hasCapabilities(): bool
    {
        return (bool) ($this->posts_freight || $this->provides_transport);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('profile');
    }
}
