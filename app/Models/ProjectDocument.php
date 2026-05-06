<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    protected $fillable = [
        'project_id',
        'original_name',
        'file_path',
        'document_type',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'invitation_to_bid' => 'Invitation to Bid',
            'bidding_documents' => 'Bidding Documents',
            'terms_of_reference' => 'Terms of Reference',
            'technical_specifications' => 'Technical Specifications',
            'bill_of_quantities' => 'Bill of Quantities',
            'project_plans' => 'Project Plans / Drawings',
            'supplemental_bulletin' => 'Supplemental Bid Bulletin',
            default => 'Other Document',
        };
    }

    public function getFileUrlAttribute(): ?string
    {
        return Uploads::url($this->file_path);
    }

    public function getDisplayNameAttribute(): ?string
    {
        return Uploads::fileName($this->file_path, $this->original_name);
    }
}
