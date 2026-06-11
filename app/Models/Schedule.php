<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One weekly slot in academy-local wall-clock time (weekday + start_time).
 * Sessions are generated from it into UTC; edits never rewrite generated
 * sessions (explicit regeneration only).
 *
 * @property int $id
 * @property int $class_type_id
 * @property int $instructor_id
 * @property int $weekday 0=Mon … 6=Sun (ISO)
 * @property string $start_time local wall clock, HH:MM:SS
 * @property int|null $duration_minutes
 * @property int|null $capacity
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on inclusive
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['class_type_id', 'instructor_id', 'weekday', 'start_time', 'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'is_active'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

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
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function effectiveDurationMinutes(): int
    {
        // class_type_id is a NOT NULL restrict FK: the parent always exists.
        return $this->duration_minutes ?? $this->classType->duration_minutes;
    }

    public function effectiveCapacity(): int
    {
        return $this->capacity ?? $this->classType->default_capacity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }
}
