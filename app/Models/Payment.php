<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * No fillable attributes: money rows are written only by the payment
 * actions/jobs (mass-assignment hardening, blueprint M3).
 *
 * @property int $id
 * @property int $booking_id
 * @property int $user_id
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentStatus $status
 * @property int|null $amount_refunded_cents
 * @property string|null $flag
 * @property string $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_refund_id
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $refund_requested_at
 * @property CarbonImmutable|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'amount_refunded_cents' => 'integer',
            'paid_at' => 'immutable_datetime',
            'refund_requested_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
