<?php

namespace App\Models;

use Database\Factories\TransporterProfileModerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporterProfileModeration extends Model
{
    protected $table = 'transporter_profile_moderation';

    /** @use HasFactory<TransporterProfileModerationFactory> */
    use HasFactory;

    protected $fillable = [
        'transporter_profile_id',
        'from_status',
        'to_status',
        'reason',
        'moderator_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TransporterProfile::class, 'transporter_profile_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
