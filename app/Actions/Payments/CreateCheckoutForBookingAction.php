<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingNotPayableException;
use App\Models\Booking;
use App\Models\Payment;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;

/**
 * The ONLY code path that creates Checkout Sessions — shared by the booking
 * tail and POST /bookings/{booking}/pay. Runs post-commit, never inside a
 * transaction.
 */
class CreateCheckoutForBookingAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    /** @return string the checkout URL to redirect to */
    public function handle(Booking $booking): string
    {
        if ($booking->status !== BookingStatus::PendingPayment) {
            throw new BookingNotPayableException;
        }

        // Repeat click: an open checkout already exists → reuse its URL.
        /** @var Payment|null $pending */
        $pending = $booking->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();
        if ($pending !== null) {
            $existing = $this->gateway->retrieveCheckoutSession($pending->stripe_checkout_session_id);
            if ($existing['status'] === 'open' && $existing['url'] !== null) {
                return $existing['url'];
            }
        }

        $session = $booking->session;
        $deadline = $booking->payment_deadline_at ?? CarbonImmutable::now()->addMinutes(30);

        // Stripe enforces a hard 30-minute floor on expires_at: clamp for
        // late waitlist offers; the LOCAL deadline stays authoritative via
        // the sweep (blueprint D8/H3).
        $expiresAt = $deadline->max(CarbonImmutable::now()->addMinutes(30));

        // attempt-versioned idempotency key: re-creation with a different
        // expires_at must not trip Stripe's same-key-different-params error.
        $attempt = $booking->payments()->count();

        $created = $this->gateway->createCheckoutSession([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount_cents' => $booking->price_cents,
            'currency' => (string) config('payments.currency'),
            'name' => $session->classType->name.' — '.$session->starts_at->toDateString(),
            'success_url' => url("/bookings/{$booking->id}/confirmation"),
            'cancel_url' => url("/sessions/{$session->id}"),
            'expires_at' => $expiresAt->getTimestamp(),
            'idempotency_key' => "checkout-booking-{$booking->id}-{$attempt}",
        ]);

        $payment = new Payment;
        $payment->booking_id = $booking->id;
        $payment->user_id = $booking->user_id;
        $payment->amount_cents = $booking->price_cents;
        $payment->currency = (string) config('payments.currency');
        $payment->status = PaymentStatus::Pending;
        $payment->stripe_checkout_session_id = $created['id'];
        $payment->save();

        return $created['url'];
    }
}
