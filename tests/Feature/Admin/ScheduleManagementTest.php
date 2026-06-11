<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;

it('lists schedules with the expected shape', function () {
    Schedule::factory()->count(2)->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/schedules')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Schedules/Index')
            ->has('schedules', 2, fn ($schedule) => $schedule
                ->hasAll(['id', 'weekday', 'start_time', 'starts_on', 'ends_on', 'is_active', 'class_type', 'instructor', 'capacity', 'duration_minutes'])
            )
        );
});

it('creates a schedule and generates its sessions inline', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $type = ClassType::factory()->create();
    $instructor = User::factory()->instructor()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->post('/admin/schedules', [
        'class_type_id' => $type->id,
        'instructor_id' => $instructor->id,
        'weekday' => 2,
        'start_time' => '18:30',
        'starts_on' => '2026-06-15',
    ])->assertRedirect('/admin/schedules');

    // Admin sees sessions immediately: 8 Wednesdays inside the 56-day horizon.
    expect(ClassSession::query()->count())->toBeGreaterThanOrEqual(8);
});

it('rejects an instructor_id that is not an instructor', function () {
    $type = ClassType::factory()->create();
    $student = User::factory()->student()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->post('/admin/schedules', [
        'class_type_id' => $type->id,
        'instructor_id' => $student->id,
        'weekday' => 2,
        'start_time' => '18:30',
        'starts_on' => '2026-06-15',
    ])->assertSessionHasErrors(['instructor_id']);
});

it('updates a schedule without rewriting generated sessions', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $schedule = Schedule::factory()->create([
        'weekday' => 1, 'start_time' => '09:00:00', 'starts_on' => '2026-06-15',
    ]);
    $this->actingAs(User::factory()->admin()->create());

    // Generate, then edit the slot time: existing sessions must NOT move.
    $this->post("/admin/schedules/{$schedule->id}/regenerate")->assertRedirect();
    $before = ClassSession::query()->orderBy('local_date')->first()?->starts_at->toIso8601String();

    $this->put("/admin/schedules/{$schedule->id}", [
        'class_type_id' => $schedule->class_type_id,
        'instructor_id' => $schedule->instructor_id,
        'weekday' => 1,
        'start_time' => '10:00',
        'starts_on' => '2026-06-15',
    ])->assertRedirect('/admin/schedules');

    expect(ClassSession::query()->orderBy('local_date')->first()?->starts_at->toIso8601String())
        ->toBe($before);

    // Explicit regeneration applies the new wall-clock time.
    $this->post("/admin/schedules/{$schedule->id}/regenerate")->assertRedirect();
    expect(ClassSession::query()->orderBy('local_date')->first()?->starts_at->toIso8601String())
        ->not->toBe($before);
});

it('validates weekday, time format and date ordering', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post('/admin/schedules', [
        'weekday' => 7,
        'start_time' => '25:99',
        'starts_on' => '2026-06-15',
        'ends_on' => '2026-06-01',
    ])->assertSessionHasErrors(['class_type_id', 'instructor_id', 'weekday', 'start_time', 'ends_on']);
});

it('forbids non-admins from managing schedules', function (string $state) {
    $schedule = Schedule::factory()->create();
    $this->actingAs(User::factory()->{$state}()->create());

    $this->get('/admin/schedules')->assertForbidden();
    $this->post('/admin/schedules', [])->assertForbidden();
    $this->post("/admin/schedules/{$schedule->id}/regenerate")->assertForbidden();
})->with(['student', 'instructor']);
