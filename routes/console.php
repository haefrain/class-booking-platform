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

// Detection layer of the seat-integrity defence (I1–I7).
Schedule::command('integrity:check')->hourly();

// Stripe-first hold release + webhook-loss reconciliation.
Schedule::command('bookings:expire-pending')->everyMinute();
