<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;

/**
 * Server-computed CTA: the Vue page is a dumb switch over this value, so
 * authorization/business rules never leak into the client (blueprint S6).
 */
class SessionViewer
{
    /**
     * @return array{cta: string, booking_id: int|null, waitlist_entry_id: int|null, cancellable_until: string|null}
     */
    public static function for(?User $user, ClassSession $session): array
    {
        return [
            'cta' => self::cta($user, $session),
            'booking_id' => self::activeBooking($user, $session)?->id,
            'waitlist_entry_id' => self::waitingEntryId($user, $session),
            'cancellable_until' => $session->starts_at
                ->subHours($session->classType->cancellation_deadline_hours)
                ->toIso8601ZuluString(),
        ];
    }

    private static function cta(?User $user, ClassSession $session): string
    {
        if ($user === null) {
            return 'login'; // guests: any would-be mutation renders as login
        }

        if ($session->isCancelled() || $session->hasStarted()) {
            return 'closed';
        }

        $booking = self::activeBooking($user, $session);
        if ($booking !== null) {
            return $booking->status === BookingStatus::PendingPayment ? 'pay' : 'cancel';
        }

        if (! $user->isStudent()) {
            return 'closed'; // admins/instructors browse, never book
        }

        if (self::waitingEntryId($user, $session) !== null) {
            return 'leave_waitlist';
        }

        if ($session->booked_count >= $session->capacity) {
            return 'join_waitlist';
        }

        // Paid checkout ships with the payments milestone; until then paid
        // classes are browse-only.
        return $session->classType->isFree() ? 'book' : 'closed';
    }

    private static function activeBooking(?User $user, ClassSession $session): ?Booking
    {
        if ($user === null) {
            return null;
        }

        return $session->bookings()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
            ->first();
    }

    private static function waitingEntryId(?User $user, ClassSession $session): ?int
    {
        if ($user === null) {
            return null;
        }

        return $session->waitlistEntries()
            ->where('user_id', $user->getKey())
            ->where('status', WaitlistStatus::Waiting)
            ->value('id');
    }
}
