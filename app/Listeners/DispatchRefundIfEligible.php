<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\CancellationKind;
use App\Enums\PaymentStatus;
use App\Events\BookingCancelled;
use App\Events\SessionCancelled;
use App\Jobs\ExpireCheckoutJob;
use App\Jobs\RefundPaymentJob;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Money policy in ONE place: on-time/admin/session cancellations refund a
 * captured payment; late cancellations free the seat but keep the money
 * (the dialog says so). Open checkouts are killed either way.
 */
class DispatchRefundIfEligible implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $backoff = 10;

    public function handle(BookingCancelled|SessionCancelled $event): void
    {
        $bookingIds = $event instanceof BookingCancelled
            ? [$event->bookingId]
            : $event->cancelledBookingIds;

        foreach ($bookingIds as $bookingId) {
            $this->process($bookingId);
        }
    }

    private function process(int $bookingId): void
    {
        $booking = Booking::query()->with('payments')->find($bookingId);

        if ($booking === null) {
            return;
        }

        // Kill any open checkout regardless of refund eligibility (C1).
        if ($booking->payments()->where('status', PaymentStatus::Pending)->exists()) {
            ExpireCheckoutJob::dispatch($booking->id)->onQueue('critical');
        }

        $refundable = $booking->cancellation_kind !== null
            && $booking->cancellation_kind !== CancellationKind::Late;

        if (! $refundable) {
            return;
        }

        $booking->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->get()
            ->each(fn ($payment) => RefundPaymentJob::dispatch($payment->id)->onQueue('critical'));
    }
}
