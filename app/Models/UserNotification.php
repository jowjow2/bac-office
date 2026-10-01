<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_demo_clock', function (Builder $query): void {
            $clock = app(\App\Support\ProcurementClock::class);
            if ($clock->demoModeEnabled()) $query->where('created_at', '<=', $clock->now());
        });
    }

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
