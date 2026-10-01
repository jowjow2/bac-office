<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Bidder extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'contact_person',
        'contact_number',
        'business_address',
        'document_path',
        'approval_status',
        'qr_token',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'review_status',
        'review_message',
        'reviewed_at',
        'reviewed_by',
        'review_requested_at',
        'review_requested_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_requested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Bidder $bidder) {
            if (Schema::hasColumn($bidder->getTable(), 'qr_token') && blank($bidder->qr_token)) {
                $bidder->qr_token = self::newQrToken();
            }
        });
    }

    public static function newQrToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Backfill a QR token for bidders that existed before this feature shipped.
     */
    public function ensureQrIdentity(): void
    {
        if (Schema::hasColumn($this->getTable(), 'qr_token') && blank($this->qr_token)) {
            $this->forceFill(['qr_token' => self::newQrToken()])->saveQuietly();
        }
    }

    /**
     * Public, read-only bidding-record verification URL encoded in the QR image.
     */
    public function verificationUrl(): ?string
    {
        if (blank($this->qr_token)) {
            return null;
        }

        return route('public.bidder.verify', ['token' => $this->qr_token]);
    }

    /**
     * QR code image (SVG) URL for the bidding-record verification link above.
     */
    public function tokenQrUrl(): ?string
    {
        if (blank($this->qr_token)) {
            return null;
        }

        return route('public.bidder.qr', ['token' => $this->qr_token]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(BidderSanction::class)->latest();
    }

    public function activeSanction(): HasOne
    {
        return $this->hasOne(BidderSanction::class)->active()->latestOfMany();
    }

    public function currentSanction(): HasOne
    {
        return $this->hasOne(BidderSanction::class)->whereNull('lifted_at')->latestOfMany();
    }

    public function hasActiveProcurementSanction(): bool
    {
        if ($this->relationLoaded('activeSanction')) {
            return $this->activeSanction !== null;
        }

        return $this->activeSanction()->exists();
    }
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requirementRequests(): HasMany
    {
        return $this->hasMany(BidderRequirementRequest::class);
    }
}
