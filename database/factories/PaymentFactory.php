<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->pendingPayment(),
            'user_id' => User::factory()->student(),
            'amount_cents' => 1500,
            'currency' => 'usd',
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
        ];
    }

    public function succeeded(): static
    {
        return $this->state([
            'status' => PaymentStatus::Succeeded,
            'stripe_payment_intent_id' => 'pi_'.Str::random(24),
            'paid_at' => CarbonImmutable::now(),
        ]);
    }

    public function refundFailed(): static
    {
        return $this->state([
            'status' => PaymentStatus::RefundFailed,
            'stripe_payment_intent_id' => 'pi_'.Str::random(24),
            'paid_at' => CarbonImmutable::now(),
            'refund_requested_at' => CarbonImmutable::now(),
        ]);
    }

    public function flagged(string $flag = 'amount_mismatch'): static
    {
        return $this->state(['flag' => $flag]);
    }
}
