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
        'valid_until' => 'date',
    ];

    /** Whether the database can keep a "Valid until" date yet (the column exists). */
    public static function tracksValidity(): bool
    {
        static $ready = null;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('bidder_documents', 'valid_until');
    }

    /**
     * Where the document stands by its validity date: null when no date was entered,
     * else ['expired'|'expiring'|'valid', days left (negative once expired)].
     */
    public function validity(int $warnDays = 30): ?array
    {
        if (! self::tracksValidity() || ! $this->valid_until) {
            return null;
        }
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $days = (int) now($zone)->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false);

        return [$days < 0 ? 'expired' : ($days <= $warnDays ? 'expiring' : 'valid'), $days];
    }

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
