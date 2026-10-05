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
        'position',
        'contact_number',
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

        if (! self::tableExists('bidders')) {
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

            if (self::tableExists('bidder_sanctions') && $profile->hasActiveProcurementSanction()) {
                return false;
            }

            return $profile->approval_status === 'approved';
        }

        return $this->status === 'active';
    }

    /** The position and contact number columns (2026_10_29 migration) are in the database. */
    public static function contactColumnsAvailable(): bool
    {
        $app = app();
        if (! $app->bound('user.contact-columns')) {
            $app->instance('user.contact-columns', Schema::hasColumns('users', ['position', 'contact_number']));
        }

        return $app->make('user.contact-columns');
    }

    /** Schema::hasTable() queries the database: remember the answer for the request. */
    private static function tableExists(string $table): bool
    {
        // Kept in the application container, so a new app (each request, each test) asks again.
        $app = app();
        $known = $app->bound('user.known-tables') ? $app->make('user.known-tables') : [];
        if (! array_key_exists($table, $known)) {
            $known[$table] = Schema::hasTable($table);
            $app->instance('user.known-tables', $known);
        }

        return $known[$table];
    }

    public function bidderProcurementStatus(): string
    {
        if ($this->role !== 'bidder') {
            return $this->status;
        }

        if (! self::tableExists('bidders')) {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        $profile = $this->relationLoaded('bidderProfile')
            ? $this->bidderProfile
            : $this->bidderProfile()->first();

        if (! $profile) {
            return $this->status === 'active' ? 'active' : $this->status;
        }

        if (self::tableExists('bidder_sanctions')) {
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

        if (! self::tableExists('bidders')) {
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

    /**
     * Offices an end-user account can be given: the standard list plus any end-user
     * office a project already names, so that project's office can record its inspections.
     */
    /**
     * Whether another bidder already uses this business registration number
     * (DTI/SEC/CDA). Case, spaces and dashes are ignored, so "DTI-2026-001"
     * and "dti 2026001" are the same number.
     */
    public static function registrationNumberTaken(?string $number, ?int $exceptUserId = null): bool
    {
        $normalized = strtoupper(preg_replace('/[\s\-]+/', '', (string) $number));
        if ($normalized === '') {
            return false;
        }

        return self::query()
            ->where('role', 'bidder')
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->whereRaw("UPPER(REPLACE(REPLACE(registration_no, ' ', ''), '-', '')) = ?", [$normalized])
            ->exists();
    }

    public static function assignableEndUserOffices(): array
    {
        $projectOffices = Project::query()
            ->whereNotNull('end_user_unit')
            ->where('end_user_unit', '!=', '')
            ->distinct()
            ->orderBy('end_user_unit')
            ->pluck('end_user_unit')
            ->map(fn ($office) => trim((string) $office))
            ->filter()
            ->all();

        return array_values(array_unique(array_merge(self::END_USER_OFFICES, $projectOffices)));
    }

    public function isEndUser(): bool
    {
        return $this->role === 'end_user';
    }
}
