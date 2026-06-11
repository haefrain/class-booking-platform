<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'user_id' => User::factory()->student(),
            'status' => WaitlistStatus::Waiting,
        ];
    }

    public function left(): static
    {
        return $this->state(['status' => WaitlistStatus::Left]);
    }

    public function expired(): static
    {
        return $this->state(['status' => WaitlistStatus::Expired]);
    }
}
