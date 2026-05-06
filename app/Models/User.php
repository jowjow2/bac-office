<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    public const STAFF_OFFICES = [
        'BAC Office',
        'Procurement Office',
        'Accounting Office',
        'Budget Office',
        'Supply Office',
        'Engineering Office',
        'Administrative Office',
        'General Services Office',
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
            ->where('document_type', 'like', 'Registration Requirement %')
            ->orderBy('id');
    }

    public function philgepsCertificate(): HasOne
    {
        return $this->hasOne(BidderDocument::class)
            ->where('document_type', 'PhilGEPS Certificate');
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
            return in_array($this->status, ['active', 'approved'], true)
                && $profile->approval_status === 'approved';
        }

        return $this->status === 'active';
    }

    public static function staffOfficeOptions(): array
    {
        return self::STAFF_OFFICES;
    }
}
