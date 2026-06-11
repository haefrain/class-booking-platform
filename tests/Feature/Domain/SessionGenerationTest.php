<?php

declare(strict_types=1);

use App\Actions\Schedules\GenerateSessionsForSchedule;
use App\Actions\Schedules\RegenerateFutureSessions;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;

function makeWeeklySchedule(array $attributes = []): Schedule
{
    return Schedule::factory()
        ->for(ClassType::factory()->state(['duration_minutes' => 60, 'default_capacity' => 12]))
        ->create($attributes);
}

it('expands a Madrid schedule across the spring DST transition', function () {
    config(['academy.timezone' => 'Europe/Madrid']);
    $this->travelTo('2026-03-16 12:00:00'); // Monday before the Mar 29 change

    // Sunday (ISO weekday 6) at 09:00 local wall clock.
    $schedule = makeWeeklySchedule([
        'weekday' => 6,
        'start_time' => '09:00:00',
        'starts_on' => '2026-03-16',
    ]);

    app(GenerateSessionsForSchedule::class)->handle($schedule);

    // CET (UTC+1) before the transition, CEST (UTC+2) after: same wall
    // clock, shifted UTC instant.
    $before = ClassSession::query()->whereDate('local_date', '2026-03-22')->sole();
    $after = ClassSession::query()->whereDate('local_date', '2026-03-29')->sole();

    expect($before->starts_at->toIso8601ZuluString())->toBe('2026-03-22T08:00:00Z')
        ->and($after->starts_at->toIso8601ZuluString())->toBe('2026-03-29T07:00:00Z');
});

it('expands a Madrid schedule across the autumn DST transition', function () {
    config(['academy.timezone' => 'Europe/Madrid']);
    $this->travelTo('2026-10-12 12:00:00'); // Monday before the Oct 25 change

    $schedule = makeWeeklySchedule([
        'weekday' => 6,
        'start_time' => '09:00:00',
        'starts_on' => '2026-10-12',
    ]);

    app(GenerateSessionsForSchedule::class)->handle($schedule);

    $before = ClassSession::query()->whereDate('local_date', '2026-10-18')->sole();
    $after = ClassSession::query()->whereDate('local_date', '2026-10-25')->sole();

    expect($before->starts_at->toIso8601ZuluString())->toBe('2026-10-18T07:00:00Z')
        ->and($after->starts_at->toIso8601ZuluString())->toBe('2026-10-25T08:00:00Z');
});

it('keeps a fixed UTC offset for Bogota (no DST)', function () {
    config(['academy.timezone' => 'America/Bogota']);
    $this->travelTo('2026-03-16 12:00:00');

    $schedule = makeWeeklySchedule([
        'weekday' => 6,
        'start_time' => '09:00:00',
        'starts_on' => '2026-03-16',
    ]);

    app(GenerateSessionsForSchedule::class)->handle($schedule);

    $sessions = ClassSession::query()->orderBy('local_date')->limit(2)->get();

    // 09:00 -05 = 14:00Z on both sides of the (non-existent) transition.
    expect($sessions[0]->starts_at->format('H:i'))->toBe('14:00')
        ->and($sessions[1]->starts_at->format('H:i'))->toBe('14:00');
});

it('generates idempotently and never rewrites existing rows', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $schedule = makeWeeklySchedule(['weekday' => 2, 'start_time' => '18:00:00', 'starts_on' => '2026-06-15']);
    $generator = app(GenerateSessionsForSchedule::class);

    $generator->handle($schedule);
    $count = ClassSession::query()->count();

    // Hand-tweak one generated row, then re-run: nothing changes.
    $session = ClassSession::query()->firstOrFail();
    $session->capacity = 99;
    $session->save();

    $generator->handle($schedule);

    expect(ClassSession::query()->count())->toBe($count)
        ->and($session->refresh()->capacity)->toBe(99);
});

it('respects the rolling horizon, starts_on and ends_on bounds', function () {
    $this->travelTo('2026-06-15 12:00:00');

    $schedule = makeWeeklySchedule([
        'weekday' => 0, // Mondays
        'start_time' => '07:00:00',
        'starts_on' => '2026-06-22',
        'ends_on' => '2026-07-06',
    ]);

    app(GenerateSessionsForSchedule::class)->handle($schedule);

    $dates = ClassSession::query()->orderBy('local_date')->pluck('local_date')
        ->map(fn ($d) => $d->toDateString())->all();

    expect($dates)->toBe(['2026-06-22', '2026-06-29', '2026-07-06']);

    // Open-ended schedule: bounded by the 56-day horizon instead.
    $open = makeWeeklySchedule(['weekday' => 0, 'start_time' => '08:00:00', 'starts_on' => '2026-06-15']);
    app(GenerateSessionsForSchedule::class)->handle($open);

    $maxDate = ClassSession::query()->where('schedule_id', $open->id)->max('local_date');
    expect($maxDate <= '2026-08-10')->toBeTrue(); // 2026-06-15 + 56d
});

it('skips inactive schedules and snapshots capacity and duration', function () {
    $this->travelTo('2026-06-15 12:00:00');

    $inactive = makeWeeklySchedule(['weekday' => 3, 'starts_on' => '2026-06-15', 'is_active' => false]);
    expect(app(GenerateSessionsForSchedule::class)->handle($inactive))->toBe(0);

    $override = makeWeeklySchedule([
        'weekday' => 3,
        'start_time' => '10:00:00',
        'starts_on' => '2026-06-15',
        'capacity' => 5,          // overrides class type default (12)
        'duration_minutes' => 90, // overrides class type default (60)
    ]);
    app(GenerateSessionsForSchedule::class)->handle($override);

    $session = ClassSession::query()->where('schedule_id', $override->id)->firstOrFail();
    expect($session->capacity)->toBe(5)
        ->and($session->starts_at->diffInMinutes($session->ends_at))->toBe(90.0)
        ->and($session->class_type_id)->toBe($override->class_type_id)
        ->and($session->instructor_id)->toBe($override->instructor_id);
});

it('generates for every active schedule via the artisan command', function () {
    $this->travelTo('2026-06-15 12:00:00');
    makeWeeklySchedule(['weekday' => 1, 'start_time' => '09:00:00', 'starts_on' => '2026-06-15']);
    makeWeeklySchedule(['weekday' => 4, 'start_time' => '18:00:00', 'starts_on' => '2026-06-15']);
    makeWeeklySchedule(['weekday' => 5, 'starts_on' => '2026-06-15', 'is_active' => false]);

    $this->artisan('sessions:generate')->assertSuccessful();

    expect(ClassSession::query()->distinct('schedule_id')->count('schedule_id'))->toBe(2);
});

it('regenerates future sessions and leaves past ones untouched', function () {
    $this->travelTo('2026-06-15 12:00:00');
    $schedule = makeWeeklySchedule(['weekday' => 0, 'start_time' => '07:00:00', 'starts_on' => '2026-06-15']);
    app(GenerateSessionsForSchedule::class)->handle($schedule);

    // A past occurrence must survive regeneration.
    $past = ClassSession::factory()->forSchedule($schedule)->past()->create();

    $schedule->capacity = 3;
    $schedule->save();
    app(RegenerateFutureSessions::class)->handle($schedule);

    $capacities = ClassSession::query()
        ->where('schedule_id', $schedule->id)
        ->where('starts_at', '>', now())
        ->pluck('capacity')->unique()->all();

    expect($capacities)->toBe([3])
        ->and(ClassSession::query()->whereKey($past->id)->exists())->toBeTrue();
});
