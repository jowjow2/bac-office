<?php

namespace App\Models\Concerns;

use App\Support\ProcurementClock;
use Carbon\CarbonImmutable;
use DateTimeInterface;

trait MasksFutureProcurementEvents
{
    /** @return list<string> */
    protected function futureProcurementEventFields(): array
    {
        return [];
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (! in_array($key, $this->futureProcurementEventFields(), true) || ! $value instanceof DateTimeInterface) {
            return $value;
        }

        return CarbonImmutable::instance($value)->greaterThan(app(ProcurementClock::class)->now()) ? null : $value;
    }
}