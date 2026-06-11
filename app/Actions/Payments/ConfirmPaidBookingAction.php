<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Waitlist\PromoteNextWaiterAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\BookingConfirmed;
use App\Jobs\RefundPaymentJob;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentAutoRefundedNotification;
use App\Notifications\SeatLostAfterPaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only path to `confirmed` for paid bookings — invoked by the completed
 * webhook AND the sweep's reconciliation. Idempotent: re-delivery hits the
 * confirmed no-op branch. Every non-confirming branch that captured money
 * dispatches a refund: money is never silently kept.
 */
class ConfirmPaidBookingAction
{
    public function __construct(
        private readonly PromoteNextWaiterAction $promoteNextWaiter,
    ) {}

    /**
     * @param  array{id: string, amount_total: int|null, currency: string|null, payment_intent: string|null}  $checkout
     */
    public function handle(array $checkout): void
    {
        /** @var array{confirmed: int|null, refund: int|null, seat_lost: int|null, refunded_after_cancel: int|null} $outcome */
        $outcome = DB::transaction(function () use ($checkout): array {
            $none = ['confirmed' => null, 'refund' => null, 'seat_lost' => null, 'refunded_after_cancel' => null];

            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('stripe_checkout_session_id', $checkout['id'])
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return $none; // webhook job releases + retries
            }

            /** @var ClassSession $session — locked FIRST, single lock ordering */
            $session = ClassSession::query()
                ->whereKey(Booking::query()->whereKey($payment->booking_id)->value('class_session_id'))
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();

            // VERIFY FIRST, before any status switch (M8/H5).
            $amountMatches = $checkout['amount_total'] === $payment->amount_cents
                && strcasecmp((string) $checkout['currency'], $payment->currency) === 0;

            if (! $amountMatches) {
                $payment->stripe_payment_intent_id = $checkout['payment_intent'];
                $payment->status = PaymentStatus::Succeeded; // ledger stays honest
                $payment->flag = 'amount_mismatch';
                $payment->save();

                if ($booking->status === BookingStatus::PendingPayment) {
                    $booking->status = BookingStatus::Expired;
                    $booking->payment_deadline_at = null; // I7
                    $booking->save();
                    $session->booked_count--;
                    $session->save();
                    $this->promoteNextWaiter->withinLockedSession($session);
                }

                Log::warning('payment amount mismatch — auto-refunding', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount_cents,
                    'received' => $checkout['amount_total'],
                ]);

                return [...$none, 'refund' => $payment->id];
            }

            switch ($booking->status) {
                case BookingStatus::PendingPayment:
                    $booking->status = BookingStatus::Confirmed;
                    $booking->payment_deadline_at = null; // I7
                    $booking->save();

                    $this->markSucceeded($payment, $checkout['payment_intent']);

                    return [...$none, 'confirmed' => $booking->id];

                case BookingStatus::Confirmed:
                    return $none; // dedup backstop

                case BookingStatus::Cancelled:
                    // (C1) The user expressed intent to leave; the seat may
                    // already belong to a promoted waiter. NEVER resurrect.
                    $this->markSucceeded($payment, $checkout['payment_intent']);

                    return [...$none, 'refund' => $payment->id, 'refunded_after_cancel' => $booking->id];

                case BookingStatus::Expired:
                    if ($this->canResurrect($session, $booking)) {
                        // The ONE sanctioned resurrection (C2), fully guarded;
                        // the counter increment is explicit so I2 holds.
                        $booking->status = BookingStatus::Confirmed;
                        $booking->save();
                        $session->booked_count++;
                        $session->save();

                        $this->markSucceeded($payment, $checkout['payment_intent']);

                        return [...$none, 'confirmed' => $booking->id];
                    }

                    $this->markSucceeded($payment, $checkout['payment_intent']);

                    return [...$none, 'refund' => $payment->id, 'seat_lost' => $booking->id];
            }
        }, attempts: 3);

        if ($outcome['confirmed'] !== null) {
            event(new BookingConfirmed($outcome['confirmed']));
        }

        if ($outcome['refund'] !== null) {
            RefundPaymentJob::dispatch($outcome['refund'])->onQueue('critical');
        }

        $this->notifyAfterCommitOutcomes($outcome);
    }

    private function markSucceeded(Payment $payment, ?string $paymentIntent): void
    {
        $payment->status = PaymentStatus::Succeeded;
        $payment->stripe_payment_intent_id = $paymentIntent;
        $payment->paid_at = CarbonImmutable::now();
        $payment->save();
    }

    private function canResurrect(ClassSession $session, Booking $booking): bool
    {
        if ($session->isCancelled() || $session->hasStarted()) {
            return false;
        }

        if ($session->booked_count >= $session->capacity) {
            return false;
        }

        return ! Booking::query()
            ->where('class_session_id', $session->getKey())
            ->where('user_id', $booking->user_id)
            ->whereKeyNot($booking->getKey())
            ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
            ->exists();
    }

    /**
     * @param  array{confirmed: int|null, refund: int|null, seat_lost: int|null, refunded_after_cancel: int|null}  $outcome
     */
    private function notifyAfterCommitOutcomes(array $outcome): void
    {
        foreach (['seat_lost' => SeatLostAfterPaymentNotification::class, 'refunded_after_cancel' => PaymentAutoRefundedNotification::class] as $key => $notification) {
            if ($outcome[$key] === null) {
                continue;
            }

            $booking = Booking::query()->with(['user', 'session.classType'])->find($outcome[$key]);
            /** @var User|null $user */
            $user = $booking?->user;
            if ($booking !== null && $user !== null) {
                $user->notify(new $notification($booking));
            }
        }
    }
}
