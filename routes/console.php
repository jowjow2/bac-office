<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bids:open-scheduled-technical', function () {
    $opened = app(\App\Support\BidOpening::class)->openDueTechnicalProjects();
    $this->info("Automatically opened technical documents for {$opened} project(s).");
})->purpose('Open technical and eligibility bid documents at their scheduled opening time');

Schedule::command('bids:open-scheduled-technical')->everyMinute()->withoutOverlapping();