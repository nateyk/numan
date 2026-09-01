<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightPostModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'freight_post_id',
        'from_status',
        'to_status',
        'reason',
        'moderator_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function freightPost(): BelongsTo
    {
        return $this->belongsTo(FreightPost::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
