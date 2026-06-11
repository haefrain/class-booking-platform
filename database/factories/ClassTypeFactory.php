<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassType>
 */
class ClassTypeFactory extends Factory
{
    protected $model = ClassType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(implode(' ', (array) fake()->unique()->words(2)));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(10),
            'duration_minutes' => fake()->randomElement([45, 60, 90]),
            'default_capacity' => fake()->numberBetween(6, 20),
            'price_cents' => 0,
            'cancellation_deadline_hours' => 24,
            'is_active' => true,
        ];
    }

    public function paid(int $priceCents = 1500): static
    {
        return $this->state(['price_cents' => $priceCents]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
