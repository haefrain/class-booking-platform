<?php

use App\Models\StripeEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

// T-24h reminders over an overlapping hourly window (at-most-once via the
// per-booking claim).
Schedule::command('bookings:send-reminders')->hourly();

// Liveness signal for the admin badge: if this stops moving, schedule:work
// is down and reminders/sweeps are silently dead.
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->toIso8601String()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Ledger hygiene: prune ONLY processed webhook rows (unprocessed ones are
// evidence for integrity:check) and stale failed jobs.
Schedule::call(fn () => StripeEvent::query()
    ->whereNotNull('processed_at')
    ->where('created_at', '<', now()->subDays(30))
    ->delete())
    ->daily()
    ->name('prune-stripe-events');
Schedule::command('queue:prune-failed', ['--hours' => 168])->weekly();
