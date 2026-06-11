<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Events\BookingConfirmed;
use App\Exceptions\AlreadyBookedException;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\SessionFullException;
use App\Exceptions\SessionNotBookableException;
use App\Exceptions\TooManyPendingHoldsException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for taking a seat. Serialization point: the session
 * row lock — every seat mutation in the system funnels through it.
 */
class BookSeatAction
{
    public function handle(User $user, int $sessionId, string $idempotencyKey): Booking
    {
        // 0. Replay fast path (no txn) — ALWAYS scoped to the caller; another
        // user's key is invisible here and collides at insert (H1).
        $existing = Booking::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->getKey())
            ->first();

        if ($existing !== null) {
            if ($existing->class_session_id !== $sessionId) {
                throw new IdempotencyKeyConflictException(
                    'This booking reference was already used for a different session.',
                );
            }

            return $existing; // benign replay (double-click / retry)
        }

        // 0b. Anti-griefing cap on unpaid holds (D16). Counted outside the
        // lock: a small over-admission race is tolerated by design.
        $pendingHolds = Booking::query()
            ->where('user_id', $user->getKey())
            ->where('status', BookingStatus::PendingPayment)
            ->count();

        if ($pendingHolds >= (int) config('booking.max_concurrent_pending_per_user')) {
            throw new TooManyPendingHoldsException;
        }

        try {
            $booking = DB::transaction(function () use ($user, $sessionId, $idempotencyKey): Booking {
                // Always re-fetch under the lock — never trust route-bound instances.
                /** @var ClassSession $session */
                $session = ClassSession::query()
                    ->whereKey($sessionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($session->isCancelled() || $session->hasStarted()) {
                    throw new SessionNotBookableException;
                }

                $alreadyActive = $session->bookings()
                    ->where('user_id', $user->getKey())
                    ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
                    ->exists();

                if ($alreadyActive) {
                    throw new AlreadyBookedException; // unique index is the backstop
                }

                if ($session->booked_count >= $session->capacity) {
                    // NEVER silently waitlist — the UI offers joining explicitly.
                    throw new SessionFullException;
                }

                // Booking a real seat supersedes a waiting entry (converted).
                $session->waitlistEntries()
                    ->where('user_id', $user->getKey())
                    ->where('status', WaitlistStatus::Waiting)
                    ->get()
                    ->each(function (WaitlistEntry $entry): void {
                        $entry->status = WaitlistStatus::Left;
                        $entry->save();
                    });

                $priceCents = $session->classType->price_cents;

                $booking = new Booking;
                $booking->class_session_id = $session->getKey();
                $booking->user_id = $user->getKey();
                $booking->source = 'direct';
                $booking->price_cents = $priceCents;
                $booking->idempotency_key = $idempotencyKey;

                if ($priceCents === 0) {
                    $booking->status = BookingStatus::Confirmed;
                } else {
                    $booking->status = BookingStatus::PendingPayment;
                    $booking->payment_deadline_at = CarbonImmutable::now()
                        ->addMinutes((int) config('booking.pending_payment_ttl_minutes'));
                }

                $booking->save();

                $session->booked_count++;
                $session->save();

                return $booking;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $e) {
            // idempotency_key collision: ours (double-click race) → return the
            // original; another user's → 409, never the existing row (H1).
            $mine = Booking::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('user_id', $user->getKey())
                ->first();

            if ($mine !== null && $mine->class_session_id === $sessionId) {
                return $mine;
            }

            throw new IdempotencyKeyConflictException;
        }

        if ($booking->status === BookingStatus::Confirmed) {
            event(new BookingConfirmed($booking->id));
        }

        return $booking;
    }
}
