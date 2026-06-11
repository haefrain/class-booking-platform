<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Schedules\GenerateSessionsForSchedule;
use App\Enums\UserRole;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Believable demo data: a small studio with free and paid classes, a busy
 * week ahead (via the real generator) and two weeks of past history.
 * Grows cumulatively with each milestone.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::query()->where('role', UserRole::Instructor)->get();
        if ($instructors->isEmpty()) {
            $instructors = User::factory()->instructor()->count(2)->create();
        }

        $types = [
            ['name' => 'Yoga Flow', 'duration' => 60, 'capacity' => 12, 'price' => 0],
            ['name' => 'Spinning', 'duration' => 45, 'capacity' => 8, 'price' => 1200],
            ['name' => 'Pilates Reformer', 'duration' => 50, 'capacity' => 6, 'price' => 2500],
            ['name' => 'Guided Meditation', 'duration' => 30, 'capacity' => 20, 'price' => 0],
        ];

        $slots = [
            ['weekday' => 0, 'time' => '07:00:00'],
            ['weekday' => 1, 'time' => '18:30:00'],
            ['weekday' => 2, 'time' => '09:00:00'],
            ['weekday' => 3, 'time' => '19:00:00'],
            ['weekday' => 5, 'time' => '10:00:00'],
            ['weekday' => 6, 'time' => '11:00:00'],
        ];

        $generator = app(GenerateSessionsForSchedule::class);

        foreach ($types as $i => $definition) {
            $type = ClassType::factory()->create([
                'name' => $definition['name'],
                'slug' => str($definition['name'])->slug()->toString(),
                'duration_minutes' => $definition['duration'],
                'default_capacity' => $definition['capacity'],
            ]);
            // Money is never mass-assigned — explicit write, as in the action.
            $type->price_cents = $definition['price'];
            $type->save();

            $slot = $slots[$i % count($slots)];
            $schedule = Schedule::factory()
                ->for($type)
                ->create([
                    'instructor_id' => $instructors[$i % $instructors->count()]->id,
                    'weekday' => $slot['weekday'],
                    'start_time' => $slot['time'],
                    'starts_on' => now(config('academy.timezone'))->toDateString(),
                ]);

            // One shared code path with the scheduler and the admin UI.
            $generator->handle($schedule);

            $this->seedPastOccurrences($schedule);
        }
    }

    /**
     * Two weeks of history per slot (deterministic local dates — the
     * (schedule, local_date) unique key forbids random past dates).
     */
    private function seedPastOccurrences(Schedule $schedule): void
    {
        $timezone = (string) config('academy.timezone');

        foreach ([1, 2] as $weeksAgo) {
            $localDate = CarbonImmutable::now($timezone)
                ->subWeeks($weeksAgo)
                ->startOfWeek()
                ->addDays($schedule->weekday);

            $startsAt = CarbonImmutable::parse(
                $localDate->toDateString().' '.$schedule->start_time,
                $timezone,
            )->utc();

            ClassSession::factory()->forSchedule($schedule)->create([
                'local_date' => $localDate->toDateString(),
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($schedule->effectiveDurationMinutes()),
                'capacity' => $schedule->effectiveCapacity(),
            ]);
        }
    }
}
