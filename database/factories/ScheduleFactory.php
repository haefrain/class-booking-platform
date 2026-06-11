<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_type_id' => ClassType::factory(),
            'instructor_id' => User::factory()->instructor(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => fake()->randomElement(['07:00:00', '09:00:00', '12:30:00', '18:00:00']),
            'duration_minutes' => null, // inherit class type
            'capacity' => null,         // inherit class type
            'starts_on' => now(config('academy.timezone'))->toDateString(),
            'ends_on' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
