<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'bid_amount',
        'proposal_file',
        'status',
        'notes',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
    ];

    public function getAmountAttribute()
    {
        return $this->bid_amount;
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['bid_amount'] = $value;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function award()
    {
        return $this->hasOne(Award::class);
    }
}
