<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Project extends Model
{
    public const PUBLIC_STATUSES = ['open', 'closed', 'awarded'];

    protected $fillable = [
        'title',
        'description',
        'document_path',
        'document_original_name',
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

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderBy('id');
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLIC_STATUSES);
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
