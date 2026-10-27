<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileModeration extends Model
{
    use HasFactory;

    protected $table = 'profile_moderations';

    protected $fillable = [
        'profile_id',
        'kind',
        'from_status',
        'to_status',
        'reason',
        'moderator_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
