<?php

declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Models\Schedule;

class CreateScheduleAction
{
    public function __construct(
        private readonly GenerateSessionsForSchedule $generator,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  validated Store payload
     */
    public function handle(array $attributes): Schedule
    {
        $schedule = Schedule::create([
            'class_type_id' => (int) $attributes['class_type_id'],
            'instructor_id' => (int) $attributes['instructor_id'],
            'weekday' => (int) $attributes['weekday'],
            'start_time' => $attributes['start_time'].':00',
            'duration_minutes' => isset($attributes['duration_minutes']) ? (int) $attributes['duration_minutes'] : null,
            'capacity' => isset($attributes['capacity']) ? (int) $attributes['capacity'] : null,
            'starts_on' => (string) $attributes['starts_on'],
            'ends_on' => isset($attributes['ends_on']) ? (string) $attributes['ends_on'] : null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);

        // Inline generation: the admin sees concrete sessions immediately.
        $this->generator->handle($schedule);

        return $schedule;
    }
}
