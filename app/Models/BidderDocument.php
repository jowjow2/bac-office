<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidderDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'original_name',
        'file_path',
        'status',
        'version',
        'is_current',
        'review_status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
        'supersedes_id',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'is_current' => 'boolean',
        'version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function getFileUrlAttribute(): ?string    {
        return Uploads::url($this->file_path);
    }

    public function getDisplayNameAttribute(): ?string
    {
        return Uploads::fileName($this->file_path, $this->original_name);
    }
}
