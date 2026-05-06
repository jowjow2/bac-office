<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'body',
        'attachment_disk',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'attachment_kind',
        'read_at',
    ];

    protected $casts = [
        'attachment_size' => 'integer',
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeBetweenUsers(Builder $query, int $firstUserId, int $secondUserId): Builder
    {
        return $query->where(function (Builder $thread) use ($firstUserId, $secondUserId) {
            $thread->where(function (Builder $nested) use ($firstUserId, $secondUserId) {
                $nested->where('sender_id', $firstUserId)
                    ->where('recipient_id', $secondUserId);
            })->orWhere(function (Builder $nested) use ($firstUserId, $secondUserId) {
                $nested->where('sender_id', $secondUserId)
                    ->where('recipient_id', $firstUserId);
            });
        });
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function isImageAttachment(): bool
    {
        return $this->attachment_kind === 'image';
    }

    public function formattedAttachmentSize(): string
    {
        $bytes = (int) $this->attachment_size;

        if ($bytes <= 0) {
            return '';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }
}
