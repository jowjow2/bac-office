<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRequirement extends Model
{
    protected $fillable = [
        'project_id',
        'eligibility_requirements',
        'technical_requirements',
        'financial_requirements',
        'required_documents',
        'qualification_notes',
        'special_instructions',
    ];

    protected $casts = [
        'required_documents' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
