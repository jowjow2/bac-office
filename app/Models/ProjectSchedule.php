<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSchedule extends Model
{
    protected $fillable = [
        'project_id',
        'date_posted',
        'pre_bid_conference_date',
        'clarification_deadline',
        'bid_submission_deadline',
        'bid_opening_date',
        'evaluation_start_date',
        'expected_award_date',
    ];

    protected $casts = [
        'date_posted' => 'date',
        'pre_bid_conference_date' => 'datetime',
        'clarification_deadline' => 'datetime',
        'bid_submission_deadline' => 'datetime',
        'bid_opening_date' => 'datetime',
        'evaluation_start_date' => 'date',
        'expected_award_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
