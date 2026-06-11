<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\ClassSession;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')
            ->addDays(fake()->numberBetween(1, 21))
            ->setTime(fake()->numberBetween(7, 19), 0);

        return [
            'schedule_id' => Schedule::factory(),
            // Mirror the parent schedule's type/instructor, as the generator does.
            // The parent factory has already persisted the schedule by the
            // time these closures resolve, so the lookup cannot miss.
            'class_type_id' => fn (array $attributes) => Schedule::query()
                ->findOrFail((int) $attributes['schedule_id'])->class_type_id,
            'instructor_id' => fn (array $attributes) => Schedule::query()
                ->findOrFail((int) $attributes['schedule_id'])->instructor_id,
            'local_date' => $startsAt->setTimezone(config('academy.timezone'))->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(60),
            'capacity' => 10,
            'booked_count' => 0,
            'status' => SessionStatus::Scheduled,
        ];
    }

    public function forSchedule(Schedule $schedule): static
    {
        return $this->state([
            'schedule_id' => $schedule->id,
            'class_type_id' => $schedule->class_type_id,
            'instructor_id' => $schedule->instructor_id,
        ]);
    }

    public function full(): static
    {
        return $this->state(['capacity' => 5, 'booked_count' => 5]);
    }

    public function past(): static
    {
        $startsAt = CarbonImmutable::now('UTC')->subDays(fake()->numberBetween(1, 14))->setTime(9, 0);

        return $this->state([
            'local_date' => $startsAt->setTimezone(config('academy.timezone'))->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(60),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => SessionStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now('UTC'),
            'cancellation_reason' => 'Instructor unavailable',
        ]);
    }
}
