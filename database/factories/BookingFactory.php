<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\CancellationKind;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'user_id' => User::factory()->student(),
            'status' => BookingStatus::Confirmed,
            'source' => 'direct',
            'price_cents' => 0,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => BookingStatus::Confirmed]);
    }

    public function pendingPayment(int $priceCents = 1500): static
    {
        return $this->state([
            'status' => BookingStatus::PendingPayment,
            'price_cents' => $priceCents,
            'payment_deadline_at' => CarbonImmutable::now()->addMinutes(30),
        ]);
    }

    public function cancelled(CancellationKind $kind = CancellationKind::OnTime): static
    {
        return $this->state([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
            'cancellation_kind' => $kind,
        ]);
    }

    public function fromWaitlist(): static
    {
        return $this->state(['source' => 'waitlist']);
    }
}
