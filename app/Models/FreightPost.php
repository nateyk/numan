<?php

namespace App\Models;

use App\Enums\FreightPostStatus;
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

    /**
     * @param  Builder<FreightPost>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FreightPostStatus::Active->value);
    }
}
