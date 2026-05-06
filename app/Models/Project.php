<?php

namespace App\Models;

use App\Support\Uploads;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Project extends Model
{
    public const PUBLIC_STATUSES = ['open', 'closed', 'awarded'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'location',
        'procurement_mode',
        'source_of_fund',
        'contract_duration',
        'budget',
        'status',
        'created_by',
        // Legacy single document fields
        'document_path',
        'document_original_name',
        // Backward compatibility deadline field
        'deadline',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'required_documents' => 'array',
        'deadline' => 'datetime',
    ];

    // Legacy deadline attribute casting (backward compatibility)
    protected $dates = [
        'deadline',
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

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderBy('id');
    }

    public function requirement()
    {
        return $this->hasOne(ProjectRequirement::class);
    }

    public function schedule()
    {
        return $this->hasOne(ProjectSchedule::class);
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLIC_STATUSES);
    }

    public function scopeHasApprovedBids(Builder $query): Builder
    {
        return $query->whereHas('bids', function ($query) {
            $query->where('status', 'approved');
        });
    }

    public function scopeReadyForAward(Builder $query): Builder
    {
        return $query->whereHas('bids', function ($query) {
            $query->where('status', 'approved');
        })->whereDoesntHave('awards');
    }

    public function getDeadlineAttribute(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        // If already a Carbon instance, return as-is
        if ($value instanceof Carbon) {
            return $value;
        }

        // Parse string to Carbon
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->firstUploadedDocument()?->file_url;
    }

    public function getDocumentFilenameAttribute(): ?string
    {
        return $this->firstUploadedDocument()?->display_name;
    }

    public function uploadedDocuments(): Collection
    {
        $documents = $this->relationLoaded('documents')
            ? $this->documents
            : $this->documents()->get();

        if (! filled($this->document_path)) {
            return $documents->values();
        }

        $legacyDocument = new ProjectDocument([
            'project_id' => $this->id,
            'file_path' => $this->document_path,
            'original_name' => $this->document_original_name,
        ]);

        return collect([$legacyDocument])
            ->concat($documents)
            ->unique('file_path')
            ->values();
    }

    public function firstUploadedDocument(): ?ProjectDocument
    {
        return $this->uploadedDocuments()->first();
    }
}
