<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaitlistStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FIFO is ORDER BY id ASC — there is deliberately no position column.
 * No fillable attributes: written exclusively by actions under the session
 * row lock.
 *
 * @property int $id
 * @property int $class_session_id
 * @property int $user_id
 * @property WaitlistStatus $status
 * @property int|null $promoted_booking_id
 * @property CarbonImmutable|null $promoted_at
 * @property-read int|null $active generated column (1 while waiting)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
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
     * @return BelongsTo<Booking, $this>
     */
    public function promotedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'promoted_booking_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaitlistStatus::class,
            'promoted_at' => 'immutable_datetime',
        ];
    }
}
