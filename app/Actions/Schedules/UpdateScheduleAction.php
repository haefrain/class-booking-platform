<?php

declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Models\Schedule;

class UpdateScheduleAction
{
    /**
     * Edits the slot definition ONLY: generated sessions are never rewritten
     * implicitly. RegenerateFutureSessions is the explicit opt-in.
     *
     * @param  array<string, mixed>  $attributes  validated Update payload
     */
    public function handle(Schedule $schedule, array $attributes): Schedule
    {
        $schedule->fill([
            'class_type_id' => (int) $attributes['class_type_id'],
            'instructor_id' => (int) $attributes['instructor_id'],
            'weekday' => (int) $attributes['weekday'],
            'start_time' => $attributes['start_time'].':00',
            'duration_minutes' => isset($attributes['duration_minutes']) ? (int) $attributes['duration_minutes'] : null,
            'capacity' => isset($attributes['capacity']) ? (int) $attributes['capacity'] : null,
            'starts_on' => (string) $attributes['starts_on'],
            'ends_on' => isset($attributes['ends_on']) ? (string) $attributes['ends_on'] : null,
            'is_active' => (bool) ($attributes['is_active'] ?? $schedule->is_active),
        ])->save();

        return $schedule->refresh();
    }
}
