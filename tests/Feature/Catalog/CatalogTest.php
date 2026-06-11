<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\User;

it('shows this week\'s upcoming sessions with week navigation', function () {
    $this->travelTo('2026-06-15 08:00:00'); // Monday

    $schedule = Schedule::factory()->create();
    $inWeek = ClassSession::factory()->forSchedule($schedule)->create([
        'local_date' => '2026-06-17',
        'starts_at' => '2026-06-17 14:00:00',
        'ends_at' => '2026-06-17 15:00:00',
    ]);
    // Outside the displayed week.
    ClassSession::factory()->forSchedule($schedule)->create([
        'local_date' => '2026-06-24',
        'starts_at' => '2026-06-24 14:00:00',
        'ends_at' => '2026-06-24 15:00:00',
    ]);
    // Cancelled never shows.
    ClassSession::factory()->forSchedule($schedule)->cancelled()->create([
        'local_date' => '2026-06-18',
        'starts_at' => '2026-06-18 14:00:00',
        'ends_at' => '2026-06-18 15:00:00',
    ]);

    $this->get('/catalog')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('sessions', 1, fn ($session) => $session
                ->where('id', $inWeek->id)
                ->hasAll(['id', 'starts_at', 'ends_at', 'capacity', 'spots_left', 'class_type', 'instructor'])
                ->etc()
            )
            ->hasAll(['week.start', 'week.prev', 'week.next'])
        );
});

it('navigates to a specific week via query param', function () {
    $this->travelTo('2026-06-15 08:00:00');
    $schedule = Schedule::factory()->create();
    $nextWeek = ClassSession::factory()->forSchedule($schedule)->create([
        'local_date' => '2026-06-24',
        'starts_at' => '2026-06-24 14:00:00',
        'ends_at' => '2026-06-24 15:00:00',
    ]);

    $this->get('/catalog?week=2026-06-22')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 1, fn ($s) => $s->where('id', $nextWeek->id)->etc())
        );
});

it('rejects a malformed week param', function () {
    $this->get('/catalog?week=garbage')->assertSessionHasErrors(['week']);
});

it('shows a session detail with login cta for guests', function () {
    $session = ClassSession::factory()->create();

    $this->get("/sessions/{$session->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sessions/Show')
            ->where('session.id', $session->id)
            ->where('viewer.cta', 'login')
            ->hasAll(['session.class_type', 'session.instructor', 'session.starts_at', 'session.spots_left'])
        );
});

it('shows closed cta to authenticated students until booking ships', function () {
    $session = ClassSession::factory()->create();
    $this->actingAs(User::factory()->student()->create());

    $this->get("/sessions/{$session->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('viewer.cta', 'closed'));
});

it('returns 404 for an unknown session', function () {
    $this->get('/sessions/999999')->assertNotFound();
});
