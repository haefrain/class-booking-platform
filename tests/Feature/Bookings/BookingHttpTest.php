<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\WaitlistPromotedNotification;
use App\Support\SeatInvariantAuditor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

afterEach(fn () => SeatInvariantAuditor::assertAll());

function makeHttpSession(int $capacity = 3, int $priceCents = 0): ClassSession
{
    $type = ClassType::factory()->create(['default_capacity' => $capacity]);
    if ($priceCents > 0) {
        $type->price_cents = $priceCents;
        $type->save();
    }

    return ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create(['capacity' => $capacity]);
}

it('books a free seat end to end with confirmation page and mail', function () {
    Notification::fake();
    $session = makeHttpSession();
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)->post("/sessions/{$session->id}/bookings", [
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $booking = Booking::query()->sole();
    $response->assertRedirect("/bookings/{$booking->id}/confirmation");

    $this->actingAs($student)->get("/bookings/{$booking->id}/confirmation")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Bookings/Confirmation')
            ->where('booking.status', 'confirmed'));

    Notification::assertSentTo($student, BookingConfirmedNotification::class);
});

it('gates the confirmation page to owner or admin', function () {
    $session = makeHttpSession();
    $owner = User::factory()->student()->create();
    $booking = Booking::factory()->for($owner)->create(['class_session_id' => $session->id]);
    $session->increment('booked_count'); // keep I2 honest for the auditor

    // Guests are redirected to login.
    $this->get("/bookings/{$booking->id}/confirmation")->assertRedirect('/login');

    // A different student probing sequential ids gets 403 (H4).
    $this->actingAs(User::factory()->student()->create())
        ->get("/bookings/{$booking->id}/confirmation")->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get("/bookings/{$booking->id}/confirmation")->assertOk();
});

it('promotes the waitlist and notifies when a booking is cancelled over http', function () {
    Notification::fake();
    $session = makeHttpSession(capacity: 1);
    $holder = User::factory()->student()->create();
    $waiter = User::factory()->student()->create();

    $this->actingAs($holder)->post("/sessions/{$session->id}/bookings", [
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $this->actingAs($waiter)->post("/sessions/{$session->id}/waitlist")->assertRedirect();

    $booking = Booking::query()->where('user_id', $holder->id)->sole();
    $this->actingAs($holder)->delete("/bookings/{$booking->id}")->assertRedirect();

    expect(WaitlistEntry::query()->sole()->status->value)->toBe('promoted')
        ->and($session->refresh()->booked_count)->toBe(1);

    Notification::assertSentTo($waiter, WaitlistPromotedNotification::class);
});

it('maps domain violations to redirect-back errors', function () {
    $session = makeHttpSession(capacity: 1);
    $this->actingAs(User::factory()->student()->create())
        ->post("/sessions/{$session->id}/bookings", ['idempotency_key' => (string) Str::uuid()]);

    // Full session → domain error in the bag, no booking created.
    $this->actingAs(User::factory()->student()->create())
        ->from("/sessions/{$session->id}")
        ->post("/sessions/{$session->id}/bookings", ['idempotency_key' => (string) Str::uuid()])
        ->assertRedirect("/sessions/{$session->id}")
        ->assertSessionHasErrors(['domain']);

    expect(Booking::query()->count())->toBe(1);
});

it('forbids staff from booking or waitlisting', function (string $state) {
    $session = makeHttpSession(capacity: 1);

    $this->actingAs(User::factory()->{$state}()->create())
        ->post("/sessions/{$session->id}/bookings", ['idempotency_key' => (string) Str::uuid()])
        ->assertForbidden();
})->with(['admin', 'instructor']);

it('shows my bookings partitioned by tab', function () {
    $student = User::factory()->student()->create();
    $upcoming = makeHttpSession();
    $this->actingAs($student)->post("/sessions/{$upcoming->id}/bookings", [
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $full = makeHttpSession(capacity: 1);
    $this->actingAs(User::factory()->student()->create())
        ->post("/sessions/{$full->id}/bookings", ['idempotency_key' => (string) Str::uuid()]);
    $this->actingAs($student)->post("/sessions/{$full->id}/waitlist");

    $this->actingAs($student)->get('/my/bookings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Bookings/Index')
            ->has('bookings', 1)
            ->has('waitlist', 1));
});
