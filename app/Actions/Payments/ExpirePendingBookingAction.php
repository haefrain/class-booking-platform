<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Waitlist\PromoteNextWaiterAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\BookingExpired;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Releases a pending-payment hold: used by the expiry sweep, the
 * checkout.session.expired webhook and the booking tail's gateway-failure
 * path. Idempotent — a non-pending booking is a no-op.
 */
class ExpirePendingBookingAction
{
    public function __construct(
        private readonly PromoteNextWaiterAction $promoteNextWaiter,
    ) {}

    public function handle(int $bookingId): void
    {
        $expired = DB::transaction(function () use ($bookingId): ?int {
            /** @var ClassSession $session */
            $session = ClassSession::query()
                ->whereKey(Booking::query()->whereKey($bookingId)->value('class_session_id'))
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();

            if ($booking->status !== BookingStatus::PendingPayment) {
                return null; // raced with confirm/cancel — state wins
            }

            $booking->status = BookingStatus::Expired;
            $booking->payment_deadline_at = null; // I7
            $booking->save();

            Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', PaymentStatus::Pending)
                ->get()
                ->each(function (Payment $payment): void {
                    $payment->status = PaymentStatus::Canceled;
                    $payment->save();
                });

            $session->booked_count--;
            $session->save();

            $this->promoteNextWaiter->withinLockedSession($session);

            return $booking->id;
        }, attempts: 3);

        if ($expired !== null) {
            event(new BookingExpired($expired));
        }
    }
}
