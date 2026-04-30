<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bid_id',
        'contract_amount',
        'contract_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'contract_amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }
}

