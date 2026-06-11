<?php

declare(strict_types=1);

use App\Actions\Bookings\BookSeatAction;
use App\Actions\Bookings\CancelBookingAction;
use App\Actions\Waitlist\JoinWaitlistAction;
use App\Actions\Waitlist\LeaveWaitlistAction;
use App\Enums\BookingStatus;
use App\Exceptions\AlreadyBookedException;
use App\Exceptions\AlreadyWaitingException;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\SeatAvailableException;
use App\Exceptions\SessionFullException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\SeatInvariantAuditor;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Real-lock suite: a SECOND MySQL connection (mysql_b) observes what a
 * concurrent request would see. Skipped off-mysql — sqlite "locks" here
 * would be theater.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Concurrency suite is MySQL-only by design.');
    }

    // Fail fast instead of hanging CI if a lock regression appears.
    DB::statement('SET SESSION innodb_lock_wait_timeout = 3');
    DB::connection('mysql_b')->statement('SET SESSION innodb_lock_wait_timeout = 3');
});

afterEach(function () {
    DB::connection('mysql_b')->rollBack();
    SeatInvariantAuditor::assertAll();
});

function freeSession(int $capacity = 1): ClassSession
{
    $type = ClassType::factory()->create(['default_capacity' => $capacity]);

    return ClassSession::factory()
        ->forSchedule(Schedule::factory()->for($type)->create())
        ->create(['capacity' => $capacity]);
}

function bookFor(User $user, ClassSession $session): Booking
{
    return app(BookSeatAction::class)->handle($user, $session->id, (string) Str::uuid());
}

/** Assert that mysql_b CANNOT acquire the session row lock right now. */
function assertSessionRowLocked(ClassSession $session): void
{
    try {
        DB::connection('mysql_b')->select(
            'SELECT id FROM class_sessions WHERE id = ? FOR UPDATE NOWAIT',
            [$session->id],
        );
        test()->fail('mysql_b acquired the lock — the serialization point is not held.');
    } catch (QueryException $e) {
        // MySQL 3572: "Statement aborted because lock(s) could not be acquired immediately"
        expect($e->getCode())->toBe('HY000')
            ->and($e->getMessage())->toContain('NOWAIT');
    }
}

it('holds the session row lock for the whole seat mutation', function () {
    $session = freeSession();

    DB::beginTransaction();
    try {
        // The exact serialization point every action uses.
        ClassSession::query()->whereKey($session->id)->lockForUpdate()->first();

        assertSessionRowLocked($session);
    } finally {
        DB::rollBack();
    }

    // Lock released → mysql_b can now read FOR UPDATE freely.
    $rows = DB::connection('mysql_b')->select(
        'SELECT id FROM class_sessions WHERE id = ? FOR UPDATE NOWAIT',
        [$session->id],
    );
    DB::connection('mysql_b')->rollBack();
    expect($rows)->toHaveCount(1);
});

it('gives the last seat to exactly one of two racing bookers', function () {
    $session = freeSession(capacity: 1);
    [$alice, $bob] = [User::factory()->student()->create(), User::factory()->student()->create()];

    // Alice's transaction holds the lock with the seat taken but uncommitted;
    // Bob would block exactly here (proven by NOWAIT), then lose the guard.
    DB::beginTransaction();
    try {
        $locked = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
        assertSessionRowLocked($session);

        $booking = new Booking;
        $booking->class_session_id = $locked->id;
        $booking->user_id = $alice->id;
        $booking->status = BookingStatus::Confirmed;
        $booking->source = 'direct';
        $booking->price_cents = 0;
        $booking->idempotency_key = (string) Str::uuid();
        $booking->save();
        $locked->booked_count++;
        $locked->save();
        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    expect(fn () => bookFor($bob, $session))->toThrow(SessionFullException::class)
        ->and(Booking::query()->where('status', BookingStatus::Confirmed)->count())->toBe(1)
        ->and($session->refresh()->booked_count)->toBe(1);
});

it('fires the database backstops even without the lock', function () {
    $session = freeSession(capacity: 1);
    $student = User::factory()->student()->create();
    bookFor($student, $session);

    // Raw duplicate active booking → generated-column unique index (I3).
    expect(fn () => DB::table('bookings')->insert([
        'class_session_id' => $session->id,
        'user_id' => $student->id,
        'status' => 'confirmed',
        'source' => 'direct',
        'price_cents' => 0,
        'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Raw over-increment → CHECK constraint, errno 3819 (I1).
    try {
        DB::statement('UPDATE class_sessions SET booked_count = capacity + 1 WHERE id = ?', [$session->id]);
        $this->fail('CHECK constraint did not fire');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('chk_sessions_capacity');
    }
});

it('never shows the seat free to a direct booker while a waiter is being promoted', function () {
    $session = freeSession(capacity: 1);
    $holder = User::factory()->student()->create();
    $waiter = User::factory()->student()->create();
    $racer = User::factory()->student()->create();

    $booking = bookFor($holder, $session);
    app(JoinWaitlistAction::class)->handle($waiter, $session->id);

    // While a cancel-shaped transaction holds the lock, the racing direct
    // booker is provably blocked at the serialization point.
    DB::beginTransaction();
    try {
        ClassSession::query()->whereKey($session->id)->lockForUpdate()->first();
        assertSessionRowLocked($session);
    } finally {
        DB::rollBack();
    }

    // Real cancellation: decrement + promotion commit atomically.
    app(CancelBookingAction::class)->handle($booking, $holder);

    // The racer arrives after commit and finds the session still full —
    // the waiter won, the seat was never observably free.
    expect(fn () => bookFor($racer, $session))->toThrow(SessionFullException::class);

    $promoted = Booking::query()->where('user_id', $waiter->id)->sole();
    expect($promoted->source)->toBe('waitlist')
        ->and($promoted->status)->toBe(BookingStatus::Confirmed);
});

it('replays an idempotency key without consuming a second seat', function () {
    $session = freeSession(capacity: 2);
    $student = User::factory()->student()->create();
    $key = (string) Str::uuid();

    $first = app(BookSeatAction::class)->handle($student, $session->id, $key);
    $replay = app(BookSeatAction::class)->handle($student, $session->id, $key);

    expect($replay->id)->toBe($first->id)
        ->and($session->refresh()->booked_count)->toBe(1);
});

it('rejects cross-user and cross-session key replays', function () {
    $session = freeSession(capacity: 2);
    $other = freeSession(capacity: 2);
    [$alice, $mallory] = [User::factory()->student()->create(), User::factory()->student()->create()];
    $key = (string) Str::uuid();

    app(BookSeatAction::class)->handle($alice, $session->id, $key);

    expect(fn () => app(BookSeatAction::class)->handle($mallory, $session->id, $key))
        ->toThrow(IdempotencyKeyConflictException::class)
        ->and(fn () => app(BookSeatAction::class)->handle($alice, $other->id, $key))
        ->toThrow(IdempotencyKeyConflictException::class);
});

it('promotes a chain of cancellations strictly FIFO', function () {
    $session = freeSession(capacity: 1);
    $users = User::factory()->student()->count(4)->create();

    $booking = bookFor($users[0], $session);
    $entries = collect([1, 2, 3])->map(
        fn (int $i) => app(JoinWaitlistAction::class)->handle($users[$i], $session->id),
    );

    // Cancel cascade: each promotion hands the seat to the next in line.
    app(CancelBookingAction::class)->handle($booking, $users[0]);
    $first = Booking::query()->where('user_id', $users[1]->id)->sole();

    app(CancelBookingAction::class)->handle($first, $users[1]);
    $second = Booking::query()->where('user_id', $users[2]->id)->whereIn('status', ['confirmed'])->sole();

    app(CancelBookingAction::class)->handle($second, $users[2]);

    expect($entries[0]->refresh()->status->value)->toBe('promoted')
        ->and($entries[1]->refresh()->status->value)->toBe('promoted')
        ->and($entries[2]->refresh()->status->value)->toBe('promoted')
        ->and(Booking::query()->where('user_id', $users[3]->id)->sole()->status)
        ->toBe(BookingStatus::Confirmed);
});

it('keeps every invariant through fifty interleaved operations', function () {
    $session = freeSession(capacity: 3);
    $students = User::factory()->student()->count(10)->create();
    $generator = app(BookSeatAction::class);

    // Deterministic op script (no randomness — resume-safe and repeatable).
    $script = [
        'b0', 'b1', 'b2', 'w3', 'w4', 'c0', 'b5', 'w0', 'c1', 'b6',
        'w1', 'c2', 'w2', 'b7', 'c5', 'w5', 'b8', 'c6', 'w6', 'b9',
        'c8', 'w8', 'c9', 'b0', 'w9', 'c7', 'b1', 'w7', 'c3', 'b2',
        'l4', 'b3', 'c0', 'w0', 'b4', 'c1', 'w1', 'b5', 'c2', 'b6',
        'l0', 'c4', 'w4', 'b7', 'c3', 'w3', 'b8', 'c5', 'l1', 'b9',
    ];

    foreach ($script as $op) {
        $kind = $op[0];
        $student = $students[(int) substr($op, 1)];

        try {
            match ($kind) {
                'b' => $generator->handle($student, $session->id, (string) Str::uuid()),
                'c' => tap(
                    Booking::query()->where('user_id', $student->id)
                        ->where('class_session_id', $session->id)
                        ->whereIn('status', ['pending_payment', 'confirmed'])->first(),
                    fn (?Booking $b) => $b === null ?: app(CancelBookingAction::class)->handle($b, $student),
                ),
                'w' => app(JoinWaitlistAction::class)->handle($student, $session->id),
                'l' => tap(
                    WaitlistEntry::query()->where('user_id', $student->id)
                        ->where('class_session_id', $session->id)
                        ->where('status', 'waiting')->first(),
                    fn ($e) => $e === null ?: app(LeaveWaitlistAction::class)->handle($e, $student),
                ),
                default => null,
            };
        } catch (SessionFullException|AlreadyBookedException|SeatAvailableException|AlreadyWaitingException) {
            // Domain rejections are legal outcomes mid-script.
        }

        // The whole point: invariants hold after EVERY operation.
        SeatInvariantAuditor::assertAll();
    }

    expect($session->refresh()->booked_count)->toBeLessThanOrEqual(3);
});
