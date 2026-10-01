<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\BidderRegistrationRequirements;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    public const STAFF_OFFICES = [
        'SJBAC',
        'Procurement Office',
        'Accounting Office',
        'Budget Office',
        'Supply Office',
        'Engineering Office',
        'Administrative Office',
        'General Services Office',
    ];

    /** Offices of the Municipality that request procurement (end-user role). */
    public const END_USER_OFFICES = [
        'Office of the Municipal Mayor',
        'Office of the Sangguniang Bayan',
        'Municipal Engineering Office',
        'Municipal Health Office',
        'Municipal Social Welfare and Development Office',
        'Municipal Agriculture Office',
        'Municipal Planning and Development Office',
        'Municipal Treasurer\'s Office',
        'Municipal Assessor\'s Office',
        'Municipal Budget Office',
        'Municipal Accounting Office',
        'Municipal Civil Registrar\'s Office',
        'Human Resource Management Office',
        'General Services Office',
        'Municipal Disaster Risk Reduction and Management Office',
        'Municipal Environment and Natural Resources Office',
        'Municipal Tourism Office',
    ];

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'status',
        'office',
        'company',
        'registration_no',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'staff_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function bidderDocuments(): HasMany
    {
        return $this->hasMany(BidderDocument::class);
    }

    public function registrationDocuments(): HasMany
    {
        return $this->hasMany(BidderDocument::class)
            ->where(function ($query) {
                $query
                    ->whereIn('document_type', BidderRegistrationRequirements::documentTypes())
                    ->orWhere('document_type', 'like', 'Registration Requirement %');
            })
            ->orderBy('id');
    }

    public function philgepsCertificate(): HasOne
    {
        return $this->hasOne(BidderDocument::class)
            ->where('document_type', 'PhilGEPS Certificate')
            ->where('is_current', true)
            ->latest('id');
    }

    public function bidderProfile(): HasOne
    {
        return $this->hasOne(Bidder::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class)->latest('created_at');
    }

    public function isApprovedBidder(): bool
    {
        if ($this->role !== 'bidder') {
            return false;
        }

        if (! Schema::hasTable('bidders')) {
            return $this->status === 'active';
        }

        if ($this->relationLoaded('bidderProfile')) {
            $profile = $this->bidderProfile;
        } else {
            $profile = $this->bidderProfile()->first();
        }

        if ($profile) {
            if ($this->status !== 'active') {
                return false;
            }

            if (Schema::hasTable('bidder_sanctions') && $profile->hasActiveProcurementSanction()) {
                return false;
            }

            return $profile->approval_status === 'approved';
        }

        return $this->status === 'active';
    }

    public function bidderProcurementStatus(): string
    {
        if ($this->role !== 'bidder') {
            return $this->status;
        }

        if (! Schema::hasTable('bidders')) {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        $profile = $this->relationLoaded('bidderProfile')
            ? $this->bidderProfile
            : $this->bidderProfile()->first();

        if (! $profile) {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        if (Schema::hasTable('bidder_sanctions')) {
            $activeSanction = $profile->relationLoaded('activeSanction')
                ? $profile->activeSanction
                : $profile->activeSanction()->first();

            if ($activeSanction) {
                return $activeSanction->type;
            }
        }

        $approvalStatus = $profile->approval_status ?: ($this->status === 'active' ? 'approved' : $this->status);

        if ($approvalStatus === 'approved') {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        if (in_array($approvalStatus, [BidderSanction::TYPE_SUSPENDED, BidderSanction::TYPE_BLACKLISTED], true)) {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        return $approvalStatus;
    }

    public function canLoginAsBidder(): bool
    {
        if ($this->role !== 'bidder' || $this->status === 'rejected') {
            return false;
        }

        if ($this->hasRestrictedBidderAccess()) {
            return true;
        }

        return $this->isApprovedBidder();
    }

    public function bidderReviewStatus(): ?string
    {
        if ($this->role !== 'bidder' || $this->status !== 'pending') {
            return null;
        }

        if (! Schema::hasTable('bidders')) {
            return 'new';
        }

        $profile = $this->relationLoaded('bidderProfile')
            ? $this->bidderProfile
            : $this->bidderProfile()->first();

        return $profile?->review_status ?: 'new';
    }

    public function hasRestrictedBidderAccess(): bool
    {
        return $this->role === 'bidder'
            && $this->status === 'pending'
            && in_array($this->bidderReviewStatus(), ['needs_action', 'for_re_evaluation'], true);
    }

    public function isPendingBidder(): bool
    {
        return $this->role === 'bidder' && $this->status === 'pending';
    }

    public static function staffOfficeOptions(): array
    {
        return self::STAFF_OFFICES;
    }

    public static function endUserOfficeOptions(): array
    {
        return self::END_USER_OFFICES;
    }

    public function isEndUser(): bool
    {
        return $this->role === 'end_user';
    }
}
