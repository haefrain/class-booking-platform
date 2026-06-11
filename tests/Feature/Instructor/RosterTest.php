<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;

it('shows an instructor their own roster only', function () {
    $instructor = User::factory()->instructor()->create();
    $own = ClassSession::factory()->create(['instructor_id' => $instructor->id]);
    Booking::factory()->count(2)->create(['class_session_id' => $own->id]);
    $own->forceFill(['booked_count' => 2])->save();
    $foreign = ClassSession::factory()->create();

    $this->actingAs($instructor)->get("/instructor/sessions/{$own->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Instructor/Sessions/Show')
            ->has('roster', 2)
            ->has('waitlist', 0));

    $this->actingAs($instructor)->get("/instructor/sessions/{$foreign->id}")
        ->assertForbidden();
});

it('lists only the instructor\'s own upcoming sessions', function () {
    $instructor = User::factory()->instructor()->create();
    ClassSession::factory()->create(['instructor_id' => $instructor->id]);
    ClassSession::factory()->create(); // someone else's

    $this->actingAs($instructor)->get('/instructor/sessions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sessions', 1));
});

it('lets the admin shrink-guard and cancel sessions over http', function () {
    $admin = User::factory()->admin()->create();
    $session = ClassSession::factory()->create(['capacity' => 2]);
    Booking::factory()->count(2)->create(['class_session_id' => $session->id]);
    $session->forceFill(['booked_count' => 2])->save();

    $this->actingAs($admin)
        ->from('/admin')
        ->patch("/admin/sessions/{$session->id}/capacity", ['capacity' => 1])
        ->assertRedirect('/admin')
        ->assertSessionHasErrors(['domain']);

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/cancel", ['reason' => 'Pipe burst'])
        ->assertRedirect();

    expect($session->refresh()->status->value)->toBe('cancelled')
        ->and($session->booked_count)->toBe(0);
});
