<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\ConfirmPaidBookingAction;
use App\Actions\Payments\ExpirePendingBookingAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\WaitlistEntry;
use App\Payments\CheckoutAlreadyCompletedException;
use App\Payments\GatewayException;
use App\Payments\PaymentGateway;
use Illuminate\Console\Command;

/**
 * The every-minute sweep that releases overdue payment holds. Stripe-first:
 * never expire locally while the checkout may still be payable. Doubles as
 * webhook-loss insurance via the AlreadyCompleted reconciliation path.
 */
class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Release pending-payment holds whose deadline passed (Stripe-first, reconciling)';

    public function handle(
        PaymentGateway $gateway,
        ConfirmPaidBookingAction $confirm,
        ExpirePendingBookingAction $expire,
    ): int {
        $overdue = Booking::query()
            ->where('status', BookingStatus::PendingPayment)
            ->where('payment_deadline_at', '<=', now())
            ->pluck('id');

        foreach ($overdue as $bookingId) {
            // One poison row never blocks the rest (M5).
            try {
                $this->expireOne((int) $bookingId, $gateway, $confirm, $expire);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Cheap cleanup: waiting entries for sessions that already started.
        WaitlistEntry::query()
            ->where('status', WaitlistStatus::Waiting)
            ->whereHas('session', fn ($query) => $query->where('starts_at', '<=', now()))
            ->get()
            ->each(function (WaitlistEntry $entry): void {
                $entry->status = WaitlistStatus::Expired;
                $entry->save();
            });

        return self::SUCCESS;
    }

    private function expireOne(
        int $bookingId,
        PaymentGateway $gateway,
        ConfirmPaidBookingAction $confirm,
        ExpirePendingBookingAction $expire,
    ): void {
        $payment = Payment::query()
            ->where('booking_id', $bookingId)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        // Gateway-orphaned hold (or never-paid waitlist offer): no checkout
        // to worry about — release directly (M5).
        if ($payment !== null) {
            try {
                // Stripe FIRST: the checkout must be dead before we free the seat.
                $gateway->expireCheckoutSession($payment->stripe_checkout_session_id);
            } catch (CheckoutAlreadyCompletedException) {
                // Paid under our feet (or the webhook never arrived):
                // reconcile locally from the source of truth (H5).
                $confirm->handle($gateway->retrieveCheckoutSession($payment->stripe_checkout_session_id));

                return;
            } catch (GatewayException $e) {
                // Still possibly payable — skip, retry next tick (S5).
                report($e);

                return;
            }
        }

        $expire->handle($bookingId);
    }
}
