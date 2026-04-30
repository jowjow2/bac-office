<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    public const PUBLIC_STATUSES = ['open', 'closed', 'awarded'];

    protected $fillable = [
        'title',
        'description',
        'budget',
        'deadline',
        'status',
        'slug',
        'created_by',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'budget' => 'decimal:2',
    ];

    // A project has many bids
    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function awards()
    {
        return $this->hasMany(Award::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'project_id');
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLIC_STATUSES);
    }
}
