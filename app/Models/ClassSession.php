<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A concrete occurrence of a schedule. Deliberately has NO fillable
 * attributes: rows are created by the generator and mutated only by actions
 * under the session row lock (booked_count, status, cancellation fields).
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $class_type_id
 * @property int $instructor_id
 * @property CarbonImmutable $local_date generation idempotency key (DST-safe)
 * @property CarbonImmutable $starts_at UTC
 * @property CarbonImmutable $ends_at UTC
 * @property int $capacity
 * @property int $booked_count
 * @property SessionStatus $status
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<ClassType, $this>
     */
    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<WaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    /**
     * Bookable catalog window: scheduled, not started.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>', now());
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    public function spotsLeft(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SessionStatus::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
