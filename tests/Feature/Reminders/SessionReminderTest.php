<?php

declare(strict_types=1);

use App\Jobs\SendSessionReminderJob;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\SessionReminderNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function bookingStartingIn(int $hours, string $timezone = 'America/Bogota'): Booking
{
    config(['academy.timezone' => $timezone]);
    $startsAt = now()->addHours($hours);

    $session = ClassSession::factory()
        ->forSchedule(Schedule::factory()->for(ClassType::factory())->create())
        ->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'local_date' => $startsAt->copy()->setTimezone($timezone)->toDateString(),
        ]);

    $booking = Booking::factory()->confirmed()->create(['class_session_id' => $session->id]);
    $session->increment('booked_count');

    return $booking;
}

it('reminds confirmed bookings inside the 23-25h window only', function (int $hoursAhead, bool $expected) {
    Notification::fake();
    $booking = bookingStartingIn($hoursAhead);

    $this->artisan('bookings:send-reminders')->assertSuccessful();

    if ($expected) {
        Notification::assertSentTo($booking->user, SessionReminderNotification::class);
        expect($booking->refresh()->reminder_sent_at)->not->toBeNull();
    } else {
        Notification::assertNothingSent();
        expect($booking->refresh()->reminder_sent_at)->toBeNull();
    }
})->with([
    '24h ahead → reminded' => [24, true],
    '10h ahead → too late to bother' => [10, false],
    '30h ahead → too early' => [30, false],
]);

it('claims at most once across duplicate dispatches and overlapping sweeps', function () {
    Notification::fake();
    $booking = bookingStartingIn(24);

    SendSessionReminderJob::dispatch($booking->id);
    SendSessionReminderJob::dispatch($booking->id); // duplicate
    $this->artisan('bookings:send-reminders');       // overlapping window re-scan

    Notification::assertSentToTimes($booking->user, SessionReminderNotification::class, 1);
});

it('skips cancelled bookings and cancelled sessions', function () {
    Notification::fake();
    $cancelled = bookingStartingIn(24);
    $cancelled->forceFill(['status' => 'cancelled'])->save();
    $cancelled->session->decrement('booked_count');

    $onDeadSession = bookingStartingIn(24);
    $onDeadSession->session->forceFill(['status' => 'cancelled'])->save();

    $this->artisan('bookings:send-reminders');

    Notification::assertNothingSent();
});

it('keeps the reminder at T-24h real hours across a DST transition', function () {
    Notification::fake();
    // Madrid, the night the clocks jump forward: 23h of wall clock are 24h
    // of real time. The window math runs on UTC instants, so the reminder
    // still fires exactly one real day ahead.
    $this->travelTo('2026-03-28 08:00:00'); // UTC, day before the change
    $booking = bookingStartingIn(24, 'Europe/Madrid');

    $this->artisan('bookings:send-reminders');

    Notification::assertSentTo($booking->user, SessionReminderNotification::class);
});

it('exposes the scheduler heartbeat on the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    Cache::put('scheduler.heartbeat', now()->toIso8601String());
    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scheduler.healthy', true));

    Cache::put('scheduler.heartbeat', now()->subMinutes(10)->toIso8601String());
    $this->actingAs($admin)->get('/admin')
        ->assertInertia(fn ($page) => $page->where('scheduler.healthy', false));

    Cache::forget('scheduler.heartbeat');
    $this->actingAs($admin)->get('/admin')
        ->assertInertia(fn ($page) => $page->where('scheduler.healthy', false));
});
