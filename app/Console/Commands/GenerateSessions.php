<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Schedules\GenerateSessionsForSchedule;
use App\Models\Schedule;
use Illuminate\Console\Command;

class GenerateSessions extends Command
{
    protected $signature = 'sessions:generate';

    protected $description = 'Top up the rolling horizon of class sessions for every active schedule';

    public function handle(GenerateSessionsForSchedule $generator): int
    {
        $inserted = 0;

        Schedule::query()
            ->where('is_active', true)
            ->with('classType')
            ->each(function (Schedule $schedule) use ($generator, &$inserted): void {
                $inserted += $generator->handle($schedule);
            });

        $this->info("Generated {$inserted} new sessions.");

        return self::SUCCESS;
    }
}
