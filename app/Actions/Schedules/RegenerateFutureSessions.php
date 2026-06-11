<?php

declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Enums\SessionStatus;
use App\Models\Schedule;

/**
 * Explicit re-expansion after a schedule edit: schedule edits never rewrite
 * generated sessions implicitly. Drops future *scheduled* occurrences and
 * regenerates them with the current slot settings; past and cancelled rows
 * are history and stay untouched.
 *
 * B3 adds the live-bookings guard: sessions with active bookings must be
 * individually cancelled before they can be regenerated.
 */
class RegenerateFutureSessions
{
    public function __construct(
        private readonly GenerateSessionsForSchedule $generator,
    ) {}

    /** @return int number of sessions inserted */
    public function handle(Schedule $schedule): int
    {
        $schedule->sessions()
            ->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>', now())
            ->delete();

        return $this->generator->handle($schedule);
    }
}
