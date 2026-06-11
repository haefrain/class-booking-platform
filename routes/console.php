<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the rolling session horizon topped up (idempotent — see
// GenerateSessionsForSchedule).
Schedule::command('sessions:generate')->dailyAt('03:10');
