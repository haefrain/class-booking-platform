<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Bookings\BookSeatAction;
use App\Actions\Schedules\GenerateSessionsForSchedule;
use App\Actions\Waitlist\JoinWaitlistAction;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Believable demo data: a small studio with free and paid classes, a busy
 * week ahead (via the real generator) and two weeks of past history.
 * Grows cumulatively with each milestone.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::query()->where('role', UserRole::Instructor)->get();
        if ($instructors->isEmpty()) {
            $instructors = User::factory()->instructor()->count(2)->create();
        }

        $types = [
            ['name' => 'Yoga Flow', 'duration' => 60, 'capacity' => 12, 'price' => 0],
            ['name' => 'Spinning', 'duration' => 45, 'capacity' => 8, 'price' => 1200],
            ['name' => 'Pilates Reformer', 'duration' => 50, 'capacity' => 6, 'price' => 2500],
            ['name' => 'Guided Meditation', 'duration' => 30, 'capacity' => 20, 'price' => 0],
        ];

        $slots = [
            ['weekday' => 0, 'time' => '07:00:00'],
            ['weekday' => 1, 'time' => '18:30:00'],
            ['weekday' => 2, 'time' => '09:00:00'],
            ['weekday' => 3, 'time' => '19:00:00'],
            ['weekday' => 5, 'time' => '10:00:00'],
            ['weekday' => 6, 'time' => '11:00:00'],
        ];

        $generator = app(GenerateSessionsForSchedule::class);

        foreach ($types as $i => $definition) {
            $type = ClassType::factory()->create([
                'name' => $definition['name'],
                'slug' => str($definition['name'])->slug()->toString(),
                'duration_minutes' => $definition['duration'],
                'default_capacity' => $definition['capacity'],
            ]);
            // Money is never mass-assigned — explicit write, as in the action.
            $type->price_cents = $definition['price'];
            $type->save();

            $slot = $slots[$i % count($slots)];
            $schedule = Schedule::factory()
                ->for($type)
                ->create([
                    'instructor_id' => $instructors[$i % $instructors->count()]->id,
                    'weekday' => $slot['weekday'],
                    'start_time' => $slot['time'],
                    'starts_on' => now(config('academy.timezone'))->toDateString(),
                ]);

            // One shared code path with the scheduler and the admin UI.
            $generator->handle($schedule);

            $this->seedPastOccurrences($schedule);
        }

        $this->seedBookingActivity();
        $this->seedPaymentHistory();
    }

    /**
     * Admin payments page demo rows: one refunded, one failed refund (retry
     * button), one flagged external refund.
     */
    private function seedPaymentHistory(): void
    {
        $cancelled = Booking::factory()
            ->cancelled()
            ->create(['price_cents' => 1200]);
        $refunded = Payment::factory()
            ->succeeded()
            ->create(['booking_id' => $cancelled->id, 'user_id' => $cancelled->user_id, 'amount_cents' => 1200]);
        $refunded->status = PaymentStatus::Refunded;
        $refunded->amount_refunded_cents = 1200;
        $refunded->refunded_at = CarbonImmutable::now()->subDay();
        $refunded->save();

        $failedBooking = Booking::factory()
            ->cancelled()
            ->create(['price_cents' => 2500]);
        Payment::factory()->refundFailed()
            ->create(['booking_id' => $failedBooking->id, 'user_id' => $failedBooking->user_id, 'amount_cents' => 2500]);

        Payment::factory()->succeeded()->flagged('external_refund')
            ->create(['amount_cents' => 1500, 'amount_refunded_cents' => 1500]);
    }

    /**
     * The Mailpit money shot, pre-staged: a popular class one seat from
     * full, and a full class with a 3-deep waitlist — all through the real
     * booking engine.
     */
    private function seedBookingActivity(): void
    {
        $book = app(BookSeatAction::class);
        $join = app(JoinWaitlistAction::class);

        $popular = ClassSession::query()->upcoming()
            ->whereRelation('classType', 'slug', 'yoga-flow')
            ->orderBy('starts_at')->first();

        if ($popular !== null) {
            User::factory()->student()->count($popular->capacity - 1)->create()
                ->each(fn (User $student) => $book->handle($student, $popular->id, (string) Str::uuid()));
        }

        $full = ClassSession::query()->upcoming()
            ->whereRelation('classType', 'slug', 'guided-meditation')
            ->orderBy('starts_at')->first();

        if ($full !== null) {
            User::factory()->student()->count($full->capacity)->create()
                ->each(fn (User $student) => $book->handle($student, $full->id, (string) Str::uuid()));

            foreach (['Wanda Waitlist', 'Walter Waiting', 'Wendy Hopeful'] as $name) {
                $waiter = User::factory()->student()->create(['name' => $name]);
                $join->handle($waiter, $full->id);
            }
        }
    }

    /**
     * Two weeks of history per slot (deterministic local dates — the
     * (schedule, local_date) unique key forbids random past dates).
     */
    private function seedPastOccurrences(Schedule $schedule): void
    {
        $timezone = (string) config('academy.timezone');

        foreach ([1, 2] as $weeksAgo) {
            $localDate = CarbonImmutable::now($timezone)
                ->subWeeks($weeksAgo)
                ->startOfWeek()
                ->addDays($schedule->weekday);

            $startsAt = CarbonImmutable::parse(
                $localDate->toDateString().' '.$schedule->start_time,
                $timezone,
            )->utc();

            ClassSession::factory()->forSchedule($schedule)->create([
                'local_date' => $localDate->toDateString(),
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($schedule->effectiveDurationMinutes()),
                'capacity' => $schedule->effectiveCapacity(),
            ]);
        }
    }
}
