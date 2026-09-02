<?php

namespace App\Models;

use Database\Factories\TransporterDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporterDocument extends Model
{
    /** @use HasFactory<TransporterDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'transporter_profile_id',
        'type',
        'original_name',
        'stored_path',
        'mime',
        'size',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TransporterProfile::class, 'transporter_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
