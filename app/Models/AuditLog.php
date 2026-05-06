<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Log an action
     */
    public static function log(string $action, $auditable, array $oldValues = null, array $newValues = null, array $context = []): self
    {
        $log = new self();
        $log->action = $action;
        $log->auditable_type = (new \ReflectionClass($auditable))->getName();
        $log->auditable_id = $auditable instanceof Model ? $auditable->getKey() : $auditable;
        $log->old_values = $oldValues;
        $log->new_values = $newValues;
        $log->ip_address = $context['ip_address'] ?? request()->ip();
        $log->user_agent = $context['user_agent'] ?? request()->userAgent();
        $log->user_id = $context['user_id'] ?? (auth()->id() ?? null);
        $log->save();

        return $log;
    }
}
