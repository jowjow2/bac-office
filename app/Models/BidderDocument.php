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
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return Uploads::url($this->file_path);
    }

    public function getDisplayNameAttribute(): ?string
    {
        return Uploads::fileName($this->file_path, $this->original_name);
    }
}
