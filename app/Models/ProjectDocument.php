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
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
