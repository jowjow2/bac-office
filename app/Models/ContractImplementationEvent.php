<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\MasksFutureProcurementEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractImplementationEvent extends Model
{    use MasksFutureProcurementEvents;

    protected function futureProcurementEventFields(): array { return ['created_at', 'occurred_at']; }

    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_demo_clock', function (Builder $query): void {
            $clock = app(\App\Support\ProcurementClock::class);
            if ($clock->demoModeEnabled()) $query->where('created_at', '<=', $clock->now())->where('occurred_at', '<=', $clock->now());
        });
    }

    protected $fillable = [
        'contract_implementation_id', 'action', 'status_from', 'status_to', 'actor_id',
        'actor_role', 'occurred_at', 'remarks', 'details', 'document_path', 'document_name',
    ];

    protected $casts = ['occurred_at' => 'datetime', 'details' => 'array'];

    public function implementation(): BelongsTo
    {
        return $this->belongsTo(ContractImplementation::class, 'contract_implementation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
