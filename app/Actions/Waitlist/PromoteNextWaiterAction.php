<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Events\WaitlistPromoted;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Runs INSIDE the seat-releasing transaction, under the session row lock the
 * caller already holds: a freed seat is never observably free while a waiter
 * exists (invariant I6) — a concurrent direct booker serializes behind the
 * lock and finds the session still full.
 */
class PromoteNextWaiterAction
{
    /**
     * Precondition: $session was fetched with lockForUpdate() in the current
     * transaction (cancel, expiry sweep, capacity grow).
     */
    public function withinLockedSession(ClassSession $session): void
    {
        $priceCents = $session->classType->price_cents;

        if ($priceCents > 0) {
            $minWindow = (int) config('booking.waitlist_min_offer_window_minutes');

            if (CarbonImmutable::now()->diffInMinutes($session->starts_at, false) < $minWindow) {
                // (H2) No waiter can be served a >= 30-min offer this close to
                // start: expire them ALL so the seat is genuinely free and I6
                // holds exactly. Free classes promote right up to start.
                $session->waitlistEntries()
                    ->where('status', WaitlistStatus::Waiting)
                    ->get()
                    ->each(function (WaitlistEntry $entry): void {
                        $entry->status = WaitlistStatus::Expired;
                        $entry->save();
                    });

                return;
            }
        }

        while ($session->booked_count < $session->capacity) {
            /** @var WaitlistEntry|null $entry */
            $entry = $session->waitlistEntries()
                ->where('status', WaitlistStatus::Waiting)
                ->orderBy('id') // FIFO, always (I5)
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                break;
            }

            $booking = new Booking;
            $booking->class_session_id = $session->getKey();
            $booking->user_id = $entry->user_id;
            $booking->source = 'waitlist';
            $booking->price_cents = $priceCents;
            $booking->idempotency_key = (string) Str::uuid();

            if ($priceCents === 0) {
                $booking->status = BookingStatus::Confirmed;
            } else {
                $booking->status = BookingStatus::PendingPayment;
                $booking->payment_deadline_at = CarbonImmutable::now()
                    ->addMinutes((int) config('booking.waitlist_offer_ttl_minutes'))
                    ->min($session->starts_at);
            }

            $booking->save();

            $entry->status = WaitlistStatus::Promoted;
            $entry->promoted_booking_id = $booking->getKey();
            $entry->promoted_at = CarbonImmutable::now();
            $entry->save();

            $session->booked_count++;
            $session->save();

            event(new WaitlistPromoted($entry->id, $booking->id));
        }
    }
}
