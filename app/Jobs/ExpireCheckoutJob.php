<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\CheckoutAlreadyCompletedException;
use App\Payments\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kills the open checkout after a cancellation committed locally (C1): the
 * user's intent never depends on Stripe uptime. If the customer pays in the
 * race window anyway, the completed webhook hits the booking's `cancelled`
 * branch and refunds — money is never silently kept.
 */
class ExpireCheckoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $bookingId,
    ) {}

    public function handle(PaymentGateway $gateway): void
    {
        $payments = Payment::query()
            ->where('booking_id', $this->bookingId)
            ->where('status', PaymentStatus::Pending)
            ->get();

        foreach ($payments as $payment) {
            try {
                $gateway->expireCheckoutSession($payment->stripe_checkout_session_id);
            } catch (CheckoutAlreadyCompletedException) {
                // Paid in the race window — the cancelled-branch refund
                // closes this; nothing to do here.
                continue;
            }

            $payment->status = PaymentStatus::Canceled;
            $payment->save();
        }
    }
}
