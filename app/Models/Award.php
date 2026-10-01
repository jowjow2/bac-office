<?php

namespace App\Models;

use App\Models\Concerns\MasksFutureProcurementEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Award extends Model
{
    use HasFactory;
    use MasksFutureProcurementEvents;

    protected function futureProcurementEventFields(): array
    {
        return ['created_at', 'certificate_uploaded_at', 'certificate_revoked_at', 'award_approved_at', 'cancelled_at', 'notice_of_award_date', 'contract_date'];
    }
    protected $fillable = [
        'verification_token',
        'certificate_number',
        'project_id',
        'bid_id',
        'bidder_id',
        'contract_amount',
        'contract_date',
        'notice_of_award_date',
        'status',
        'notes',
        'certificate_file_path',
        'qr_token',
        'certificate_status',
        'certificate_uploaded_at',
        'certificate_revoked_at',
        'certificate_revoked_by',
        'award_approved_at',
        'award_approved_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reference',
        'cancellation_reason',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'notice_of_award_date' => 'date',
        'contract_amount' => 'decimal:2',
        'certificate_uploaded_at' => 'datetime',
        'certificate_revoked_at' => 'datetime',
        'award_approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Certificate status constants
    public const STATUS_VALID = 'valid';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Certificate status of an award the HoPE approved whose Notice of Award is
     * not issued yet: listed in Awards & Contracts, never posted publicly.
     */
    public const CERTIFICATE_PENDING_NOTICE = 'pending';

    /** Approved, Notice of Award not issued yet. */
    public function awaitsNoticeOfAward(): bool
    {
        return ! $this->isCancelled() && $this->certificate_status === self::CERTIFICATE_PENDING_NOTICE;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Awards posted to the public: Notice of Award issued, not revoked or
     * cancelled, and its date reached by the server clock in Philippine time
     * (a date without a time is posted at 12:00 AM of that date).
     */
    public function scopePubliclyPosted($query, ?\Carbon\CarbonInterface $at = null)
    {
        $today = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();

        return $query->whereNull('cancelled_at')
            ->where(fn ($builder) => $builder->whereNull('certificate_status')->orWhereNotIn('certificate_status', [self::STATUS_REVOKED, self::CERTIFICATE_PENDING_NOTICE]))
            ->where(fn ($builder) => $builder->whereNull('status')->orWhere('status', '!=', self::STATUS_REVOKED))
            ->where(fn ($builder) => $builder->whereDate('notice_of_award_date', '<=', $today)
                ->orWhere(fn ($legacy) => $legacy->whereNull('notice_of_award_date')
                    ->where(fn ($contract) => $contract->whereNull('contract_date')->orWhereDate('contract_date', '<=', $today))));
    }

    /** 12:00 AM Philippine time of the award (NOA) date: when the award may be posted publicly. */
    public function publicationTime(): ?\Carbon\CarbonInterface
    {
        $date = $this->awardDate();

        return $date ? \Illuminate\Support\Carbon::parse($date->toDateString(), config('app.timezone', 'Asia/Manila'))->startOfDay() : null;
    }

    /** Issued but dated ahead: hidden from the public, shown to the BAC as Scheduled. */
    public function isScheduledForPublication(?\Carbon\CarbonInterface $at = null): bool
    {
        $time = $this->publicationTime();

        return ! $this->isCancelled() && ! $this->awaitsNoticeOfAward() && $time !== null && $time->isAfter($at ?? now());
    }

    /** Listed, searchable, verifiable and downloadable by the public now. */
    public function isPubliclyVisible(?\Carbon\CarbonInterface $at = null): bool
    {
        return ! $this->isCancelled()
            && ! $this->awaitsNoticeOfAward()
            && ! $this->isRevoked()
            && ! $this->isScheduledForPublication($at);
    }

    /** An award still in force: not cancelled or revoked. */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at')->whereIn('status', [self::STATUS_VALID, 'active']);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'award_approved_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function getValidStatuses(): array
    {
        return [self::STATUS_VALID];
    }

    public static function getInvalidStatuses(): array
    {
        return [self::STATUS_REVOKED, self::STATUS_EXPIRED];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_procurement_clock', function (Builder $query): void {
            $query->where('created_at', '<=', app(\App\Support\ProcurementClock::class)->now());
        });

        static::creating(function (Award $award) {
            if (Schema::hasColumn($award->getTable(), 'verification_token') && blank($award->verification_token)) {
                $award->verification_token = self::newVerificationToken();
            }

            if (Schema::hasColumn($award->getTable(), 'qr_token') && blank($award->qr_token)) {
                $award->qr_token = self::newQrToken();
            }

            if (Schema::hasColumn($award->getTable(), 'certificate_status') && blank($award->certificate_status)) {
                $award->certificate_status = self::STATUS_VALID;
            }
        });

        static::created(function (Award $award) {
            if (Schema::hasColumn($award->getTable(), 'certificate_number') && blank($award->certificate_number)) {
                $award->forceFill([
                    'certificate_number' => self::certificateNumberFor($award->id),
                ])->saveQuietly();
            }
        });

        static::deleting(function (Award $award) {
            if ($award->certificate_file_path && Storage::disk('local')->exists($award->certificate_file_path)) {
                Storage::disk('local')->delete($award->certificate_file_path);
            }
        });
    }

    public static function newVerificationToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('verification_token', $token)->exists());

        return $token;
    }

    public static function newQrToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::query()->where('qr_token', $token)->exists());

        return $token;
    }

    public static function certificateNumberFor(int $id): string
    {
        return 'BAC-AWD-' . now()->format('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function ensureCertificateIdentity(): void
    {
        $updates = [];

        if (Schema::hasColumn($this->getTable(), 'verification_token') && blank($this->verification_token)) {
            $updates['verification_token'] = self::newVerificationToken();
        }

        if (Schema::hasColumn($this->getTable(), 'certificate_number') && blank($this->certificate_number)) {
            $updates['certificate_number'] = self::certificateNumberFor($this->id);
        }

        if (Schema::hasColumn($this->getTable(), 'qr_token') && blank($this->qr_token)) {
            $updates['qr_token'] = self::newQrToken();
        }

        if (Schema::hasColumn($this->getTable(), 'certificate_status') && blank($this->certificate_status)) {
            $updates['certificate_status'] = self::STATUS_VALID;
        }

        if ($updates !== []) {
            $this->forceFill($updates)->saveQuietly();
            $this->refresh();
        }
    }

    /**
     * Date of award = date the Notice of Award was issued. Older records only
     * have contract_date, which held the declaration date.
     */
    public function awardDate(): ?\Carbon\CarbonInterface
    {
        $noa = $this->getRawOriginal('notice_of_award_date');
        $contract = $this->getRawOriginal('contract_date');
        return $noa ? \Illuminate\Support\Carbon::parse($noa) : ($contract ? \Illuminate\Support\Carbon::parse($contract) : null);
    }
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'certificate_revoked_by');
    }

    /**
     * Check if certificate file exists in storage
     */
    public function hasCertificateFile(): bool
    {
        return filled($this->certificate_file_path) && Storage::disk('local')->exists($this->certificate_file_path);
    }

    /**
     * Get the certificate file name for download
     */
    public function getCertificateFileName(): string
    {
        return 'certificate_' . $this->certificate_number . '.pdf';
    }

    /**
     * Check if certificate is valid for viewing
     */
    public function isCertificateViewable(): bool
    {
        $certificateStatus = $this->certificate_status ?: $this->status;

        return $certificateStatus === self::STATUS_VALID && $this->hasCertificateFile() && filled($this->qr_token);
    }

    /**
     * Check if certificate is revoked
     */
    public function isRevoked(): bool
    {
        return ($this->certificate_status ?: $this->status) === self::STATUS_REVOKED;
    }

    /**
     * Check if certificate is expired
     */
    public function isExpired(): bool
    {
        return ($this->certificate_status ?: $this->status) === self::STATUS_EXPIRED;
    }

    /**
     * Token-based certificate access URL
     */
    public function tokenCertificateUrl(): ?string
    {
        if (blank($this->qr_token)) {
            return null;
        }

        return route('certificate.view', [
            'token' => $this->qr_token,
        ]);
    }

    /**
     * Public, read-only award verification URL.
     */
    public function verificationUrl(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return route('certificate.verify', [
            'award' => $this->getKey(),
        ]);
    }

    /**
     * Token-based QR code URL
     */
    public function tokenQrUrl(): ?string
    {
        if (blank($this->qr_token)) {
            return null;
        }

        return route('public.qr.show', [
            'token' => $this->qr_token,
        ]);
    }

    /**
     * Legacy certificate URL (kept for compatibility)
     */
    public function certificateUrl(): ?string
    {
        return $this->tokenCertificateUrl();
    }

    /**
     * Legacy QR URL (kept for compatibility)
     */
    public function qrUrl(): ?string
    {
        return $this->tokenQrUrl();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }

    public function bidder()
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    public function contractImplementation()
    {
        return $this->hasOne(ContractImplementation::class);
    }
}
