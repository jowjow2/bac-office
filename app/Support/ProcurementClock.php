<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\Carbon as BaseCarbon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The authoritative time source for procurement schedules. Demo time is local-only and opt-in. */
class ProcurementClock
{
    public const TIMEZONE = 'Asia/Manila';
    private const SETTING_ID = 1;
    private static bool $resolvedForRequest = false;
    private static ?CarbonImmutable $requestTime = null;
    private static bool $frozeCarbon = false;

    public function demoModeEnabled(): bool
    {
        $local = app()->environment('local');
        $explicitTest = app()->runningUnitTests() && (bool) config('bac-office.demo_clock.allow_testing', false);

        return (bool) config('bac-office.demo_clock.enabled', false) && ! app()->environment('production') && config('app.env') !== 'production' && ($local || $explicitTest);
    }

    public function timezone(): string
    {
        return self::TIMEZONE;
    }

    public function now(): CarbonImmutable
    {
        if (! $this->demoModeEnabled()) return CarbonImmutable::now(self::TIMEZONE);
        if (! self::$resolvedForRequest) $this->resolveForRequest();

        return self::$requestTime ?? CarbonImmutable::now(self::TIMEZONE);
    }

    public function simulatedTime(): ?CarbonImmutable
    {
        if (! $this->demoModeEnabled() || ! Schema::hasTable('procurement_demo_clock')) return null;
        if (! self::$resolvedForRequest) $this->resolveForRequest();

        return self::$requestTime;
    }

    private function resolveForRequest(): void
    {
        self::$resolvedForRequest = true;
        if (! Schema::hasTable('procurement_demo_clock')) return;
        $value = DB::table('procurement_demo_clock')->where('id', self::SETTING_ID)->value('simulated_at');
        self::$requestTime = $value ? CarbonImmutable::parse($value, self::TIMEZONE)->setTimezone(self::TIMEZONE) : null;
    }

    public function beginRequest(): void
    {
        if (self::$frozeCarbon) {
            Carbon::setTestNow();
            BaseCarbon::setTestNow();
            self::$frozeCarbon = false;
        }
        self::$resolvedForRequest = false;
        self::$requestTime = null;
    }

    /** Freeze every Laravel/Carbon now() call for this request to the same procurement time. */
    public function applyToRequest(): void
    {
        $this->beginRequest();
        if (! $this->demoModeEnabled()) return;
        $now = $this->simulatedTime();
        if ($now !== null) {
            Carbon::setTestNow($now);
            BaseCarbon::setTestNow($now);
            self::$frozeCarbon = true;
        }
    }

    public function set(CarbonImmutable $at, int $actorId): void
    {
        abort_unless($this->demoModeEnabled(), 404);
        abort_unless(Schema::hasTable('procurement_demo_clock'), 503, 'Run migrations to enable the demo clock.');
        $at = $at->setTimezone(self::TIMEZONE);
        DB::table('procurement_demo_clock')->updateOrInsert(
            ['id' => self::SETTING_ID],
            ['simulated_at' => $at->format('Y-m-d H:i:s'), 'updated_by' => $actorId]
        );
        self::$resolvedForRequest = true;
        self::$requestTime = $at;
        Carbon::setTestNow($at);
        BaseCarbon::setTestNow($at);
        self::$frozeCarbon = true;
    }

    public function reset(): void
    {
        abort_unless($this->demoModeEnabled(), 404);
        if (Schema::hasTable('procurement_demo_clock')) DB::table('procurement_demo_clock')->where('id', self::SETTING_ID)->delete();
        self::$resolvedForRequest = true;
        self::$requestTime = null;
        Carbon::setTestNow();
        BaseCarbon::setTestNow();
        self::$frozeCarbon = false;
    }
}