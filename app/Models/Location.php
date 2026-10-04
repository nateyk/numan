<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'region_name',
        'latitude',
        'longitude',
        'is_active',
        'population',
        'elevation',
        'wereda',
        'zone',
        'is_major_town',
        'osm_id',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'is_major_town' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Location>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Location>  $query
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('region_name', 'like', "%{$term}%")
                ->orWhere('wereda', 'like', "%{$term}%");
        });
    }

    /**
     * @param  Builder<Location>  $query
     */
    public function scopeMajorTowns(Builder $query): Builder
    {
        return $query->where('is_major_town', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->type} • {$this->region_name})";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('location');
    }
}
