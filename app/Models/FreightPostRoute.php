<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightPostRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'freight_post_id',
        'distance_meters',
        'duration_seconds',
        'geometry',
        'routing_provider',
    ];

    protected function casts(): array
    {
        return [
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'geometry' => 'array',
        ];
    }

    public function freightPost(): BelongsTo
    {
        return $this->belongsTo(FreightPost::class);
    }
}
