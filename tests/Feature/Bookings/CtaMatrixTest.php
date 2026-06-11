<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WaitlistEntry;

/**
 * The full server-computed CTA matrix, table-driven (blueprint S6/S9).
 * Paid `pay` states are exercised from the payments milestone on.
 */
dataset('cta-matrix', [
    'guest, free seat available' => ['guest', 'available', 'login'],
    'guest, full session' => ['guest', 'full', 'login'],
    'student, free seat available' => ['student', 'available', 'book'],
    'student, full session' => ['student', 'full', 'join_waitlist'],
    'student, already waiting' => ['student', 'waiting', 'leave_waitlist'],
    'student, holds confirmed booking' => ['student', 'booked', 'cancel'],
    'student, paid class (pre-payments)' => ['student', 'paid', 'closed'],
    'student, cancelled session' => ['student', 'cancelled', 'closed'],
    'student, started session' => ['student', 'started', 'closed'],
    'admin browsing' => ['admin', 'available', 'closed'],
    'instructor browsing' => ['instructor', 'available', 'closed'],
]);

it('computes the right cta for every viewer/session state', function (string $viewer, string $state, string $expected) {
    $type = ClassType::factory()->create(['default_capacity' => 2]);
    if ($state === 'paid') {
        $type->price_cents = 1500;
        $type->save();
    }

    $session = ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create(['capacity' => 2]);

    $user = $viewer === 'guest' ? null : User::factory()->{$viewer}()->create();

    match ($state) {
        'full' => Booking::factory()->count(2)->create(['class_session_id' => $session->id])
            ->each(fn () => $session->increment('booked_count')),
        'waiting' => (function () use ($session, $user): void {
            Booking::factory()->count(2)->create(['class_session_id' => $session->id])
                ->each(fn () => $session->increment('booked_count'));
            WaitlistEntry::factory()->create(['class_session_id' => $session->id, 'user_id' => $user?->id]);
        })(),
        'booked' => (function () use ($session, $user): void {
            Booking::factory()->create(['class_session_id' => $session->id, 'user_id' => $user?->id]);
            $session->increment('booked_count');
        })(),
        'cancelled' => $session->forceFill(['status' => 'cancelled'])->save(),
        'started' => $session->forceFill([
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
        ])->save(),
        default => null,
    };

    $request = $user === null ? $this : $this->actingAs($user);

    $request->get("/sessions/{$session->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('viewer.cta', $expected));
})->with('cta-matrix');
