<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CancellationKind;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A seat-holder row (waitlist lives in waitlist_entries). Deliberately has
 * NO fillable attributes: rows are written exclusively by actions under the
 * session row lock.
 *
 * @property int $id
 * @property int $class_session_id
 * @property int $user_id
 * @property BookingStatus $status
 * @property string $source direct | waitlist
 * @property int $price_cents snapshot at creation
 * @property string $idempotency_key
 * @property CarbonImmutable|null $payment_deadline_at non-null IFF pending_payment (I7)
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property CancellationKind|null $cancellation_kind
 * @property CarbonImmutable|null $reminder_sent_at
 * @property-read int|null $active generated column (1 for live states)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'price_cents' => 'integer',
            'payment_deadline_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'cancellation_kind' => CancellationKind::class,
            'reminder_sent_at' => 'immutable_datetime',
        ];
    }
}
