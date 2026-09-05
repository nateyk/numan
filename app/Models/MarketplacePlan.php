<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplacePlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_weekly_etb',
        'price_monthly_etb',
        'price_yearly_etb',
        'is_active',
        'is_popular',
        'sort_order',
        'limits',
        'features',
        'faq',
        'chapa_plan_code',
    ];

    protected function casts(): array
    {
        return [
            'price_weekly_etb' => 'decimal:2',
            'price_monthly_etb' => 'decimal:2',
            'price_yearly_etb' => 'decimal:2',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'limits' => 'array',
            'features' => 'array',
            'faq' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
