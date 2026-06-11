<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Actions\Waitlist\PromoteNextWaiterAction;
use App\Enums\BookingStatus;
use App\Enums\CancellationKind;
use App\Events\BookingCancelled;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CancelBookingAction
{
    public function __construct(
        private readonly PromoteNextWaiterAction $promoteNextWaiter,
    ) {}

    public function handle(Booking $booking, User $actor): Booking
    {
        $cancelled = DB::transaction(function () use ($booking, $actor): Booking {
            // Session lock FIRST, then the booking row — one ordering, no deadlocks.
            /** @var ClassSession $session */
            $session = ClassSession::query()
                ->whereKey($booking->class_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Booking $fresh */
            $fresh = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(BookingStatus::Cancelled)) {
                throw new InvalidBookingTransitionException($fresh->status, BookingStatus::Cancelled);
            }

            $fresh->status = BookingStatus::Cancelled;
            $fresh->cancelled_at = CarbonImmutable::now();
            $fresh->cancelled_by = $actor->getKey();
            $fresh->cancellation_kind = $this->kindFor($fresh, $session, $actor);
            $fresh->payment_deadline_at = null; // I7
            $fresh->save();

            $session->booked_count--;
            $session->save();

            // Same transaction, same lock: the seat is never observably free
            // while a waiter exists.
            $this->promoteNextWaiter->withinLockedSession($session);

            return $fresh;
        }, attempts: 3);

        event(new BookingCancelled($cancelled->id));

        return $cancelled;
    }

    private function kindFor(Booking $booking, ClassSession $session, User $actor): CancellationKind
    {
        if ($actor->isAdmin() && $actor->getKey() !== $booking->user_id) {
            return CancellationKind::Admin;
        }

        $deadline = $session->starts_at->subHours($session->classType->cancellation_deadline_hours);

        // Late cancellation is ALLOWED — the seat frees for the waitlist;
        // the dialog warns there is no refund.
        return CarbonImmutable::now()->lte($deadline)
            ? CancellationKind::OnTime
            : CancellationKind::Late;
    }
}
