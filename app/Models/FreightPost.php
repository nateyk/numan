<?php

namespace App\Models;

use App\Enums\FreightPostStatus;
use App\Services\FreightCompletionService;
use Carbon\Carbon;
use Database\Factories\FreightPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FreightPost extends Model
{
    /** @use HasFactory<FreightPostFactory> */
    use HasFactory;

    protected $fillable = [
        'ulid',
        'user_id',
        'origin_location_id',
        'destination_location_id',
        'origin_exact_lat',
        'origin_exact_lng',
        'destination_exact_lat',
        'destination_exact_lng',
        'origin_site_note',
        'destination_site_note',
        'origin_point_confirmed_at',
        'destination_point_confirmed_at',
        'cargo_type_id',
        'cargo_description',
        'item_name',
        'actual_weight',
        'weight_unit',
        'tariff_amount',
        'tariff_currency',
        'insurance_required',
        'insurance_value',
        'depreciation_rate',
        'required_truck_type_id',
        'truck_count',
        'schedule_type',
        'pickup_date',
        'pickup_time',
        'pickup_date_flexibility_days',
        'status',
        'completion_score',
    ];

    protected function casts(): array
    {
        return [
            'origin_exact_lat' => 'decimal:7',
            'origin_exact_lng' => 'decimal:7',
            'destination_exact_lat' => 'decimal:7',
            'destination_exact_lng' => 'decimal:7',
            'origin_point_confirmed_at' => 'datetime',
            'destination_point_confirmed_at' => 'datetime',
            'pickup_date' => 'date',
            'truck_count' => 'integer',
            'pickup_date_flexibility_days' => 'integer',
            'completion_score' => 'integer',
            'actual_weight' => 'decimal:2',
            'tariff_amount' => 'decimal:2',
            'insurance_required' => 'boolean',
            'insurance_value' => 'decimal:2',
            'depreciation_rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function cargoType(): BelongsTo
    {
        return $this->belongsTo(CargoType::class, 'cargo_type_id');
    }

    public function truckType(): BelongsTo
    {
        return $this->belongsTo(TruckType::class, 'required_truck_type_id');
    }

    public function routeSnapshot(): HasOne
    {
        return $this->hasOne(FreightPostRoute::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(FreightPostModerationLog::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(FreightInterest::class);
    }

    /**
     * @param  Builder<FreightPost>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FreightPostStatus::Active->value);
    }

    /**
     * @param  Builder<FreightPost>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<FreightPost>  $query
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', FreightPostStatus::Expired->value);
    }

    public function expireIfPastPickup(): bool
    {
        if ($this->status !== FreightPostStatus::Active->value || ! $this->pickup_date) {
            return false;
        }

        $pickup = $this->pickup_date instanceof Carbon ? $this->pickup_date : Carbon::parse($this->pickup_date);
        $flex = (int) ($this->pickup_date_flexibility_days ?? 0);
        $expiresAt = $pickup->copy()->addDays($flex)->endOfDay();

        if ($expiresAt->isPast()) {
            $this->update(['status' => FreightPostStatus::Expired->value]);

            return true;
        }

        return false;
    }

    /**
     * @param  Builder<FreightPost>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term): void {
            $q->where('cargo_description', 'like', "%{$term}%")
                ->orWhere('ulid', 'like', "%{$term}%");
        });
    }

    /**
     * @param  Builder<FreightPost>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['origin'] ?? null, fn ($q, $v) => $q->where('origin_location_id', $v))
            ->when($filters['destination'] ?? null, fn ($q, $v) => $q->where('destination_location_id', $v))
            ->when($filters['cargo_type'] ?? null, fn ($q, $v) => $q->where('cargo_type_id', $v))
            ->when($filters['truck_type'] ?? null, fn ($q, $v) => $q->where('required_truck_type_id', $v));
    }

    public function completionScore(): int
    {
        return app(FreightCompletionService::class)->score($this);
    }
}
