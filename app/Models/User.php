<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function bidderDocuments(): HasMany
    {
        return $this->hasMany(BidderDocument::class);
    }

    public function philgepsCertificate(): HasOne
    {
        return $this->hasOne(BidderDocument::class)
            ->where('document_type', 'PhilGEPS Certificate');
    }

    public static function staffOfficeOptions(): array
    {
        return self::STAFF_OFFICES;
    }
}
