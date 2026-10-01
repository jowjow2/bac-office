<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequestDocument extends Model
{
    protected $fillable = [
        'procurement_request_id',
        'document_type',
        'original_name',
        'file_path',
        'uploaded_by',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id');
    }

    public function typeLabel(): string
    {
        return ProcurementRequest::DOCUMENT_TYPES[$this->document_type] ?? 'Attachment';
    }
}
