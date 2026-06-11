<?php

declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Enums\SessionStatus;
use App\Models\ClassSession;
use App\Models\Schedule;
use Carbon\CarbonImmutable;

/**
 * Expands a weekly academy-local slot into concrete UTC sessions over the
 * rolling horizon. Idempotent by construction: insertOrIgnore keyed on
 * UNIQUE(schedule_id, local_date) — existing rows are NEVER updated or
 * deleted (the local date stays stable across DST while the UTC instant
 * shifts; that is the whole point of keying on it).
 */
class GenerateSessionsForSchedule
{
    /** @return int number of sessions inserted */
    public function handle(Schedule $schedule): int
    {
        if (! $schedule->is_active) {
            return 0;
        }

        $timezone = (string) config('academy.timezone');
        $todayLocal = CarbonImmutable::now($timezone)->toDateString();

        // Pure date arithmetic on floating dates; ISO strings compare
        // lexicographically.
        $from = max($schedule->starts_on->toDateString(), $todayLocal);
        $until = CarbonImmutable::parse($todayLocal)
            ->addDays((int) config('booking.horizon_days'))
            ->toDateString();

        if ($schedule->ends_on !== null) {
            $until = min($until, $schedule->ends_on->toDateString());
        }

        $cursor = CarbonImmutable::parse($from);

        // First occurrence of the slot's weekday on/after $from (0=Mon…6=Sun).
        $offset = ($schedule->weekday + 1) - $cursor->dayOfWeekIso;
        $cursor = $cursor->addDays($offset < 0 ? $offset + 7 : $offset);

        $durationMinutes = $schedule->effectiveDurationMinutes();
        $capacity = $schedule->effectiveCapacity();
        $now = now();

        $rows = [];
        for (; $cursor->toDateString() <= $until; $cursor = $cursor->addDays(7)) {
            // DST resolution happens HERE: same wall clock, tz-aware instant.
            $localStart = CarbonImmutable::parse(
                $cursor->toDateString().' '.$schedule->start_time,
                $timezone,
            );
            $startsAtUtc = $localStart->utc();

            $rows[] = [
                'schedule_id' => $schedule->id,
                'class_type_id' => $schedule->class_type_id,
                'instructor_id' => $schedule->instructor_id,
                'local_date' => $cursor->toDateString(),
                'starts_at' => $startsAtUtc->toDateTimeString(),
                'ends_at' => $startsAtUtc->addMinutes($durationMinutes)->toDateTimeString(),
                'capacity' => $capacity,
                'booked_count' => 0,
                'status' => SessionStatus::Scheduled->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return ClassSession::query()->insertOrIgnore($rows);
    }
}
