<?php

namespace App\Models;

use Database\Factories\TransporterRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporterRoute extends Model
{
    /** @use HasFactory<TransporterRouteFactory> */
    use HasFactory;

    protected $fillable = [
        'transporter_profile_id',
        'origin_location_id',
        'destination_location_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TransporterProfile::class, 'transporter_profile_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
