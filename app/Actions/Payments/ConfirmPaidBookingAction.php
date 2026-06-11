<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only path to `confirmed` for paid bookings — invoked by the completed
 * webhook AND the sweep's reconciliation. Idempotent: re-delivery hits the
 * confirmed no-op branch.
 */
class ConfirmPaidBookingAction
{
    /**
     * @param  array{id: string, amount_total: int|null, currency: string|null, payment_intent: string|null}  $checkout
     */
    public function handle(array $checkout): void
    {
        $confirmedBookingId = DB::transaction(function () use ($checkout): ?int {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('stripe_checkout_session_id', $checkout['id'])
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return null; // caller decides (webhook job releases + retries)
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

                Log::warning('payment amount mismatch — booking left unconfirmed', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount_cents,
                    'received' => $checkout['amount_total'],
                ]);

                // Full mismatch handling (auto-refund + seat release) lands
                // with the refund pipeline milestone.
                return null;
            }

            switch ($booking->status) {
                case BookingStatus::PendingPayment:
                    $booking->status = BookingStatus::Confirmed;
                    $booking->payment_deadline_at = null; // I7
                    $booking->save();

                    $payment->status = PaymentStatus::Succeeded;
                    $payment->stripe_payment_intent_id = $checkout['payment_intent'];
                    $payment->paid_at = CarbonImmutable::now();
                    $payment->save();

                    return $booking->id;

                case BookingStatus::Confirmed:
                    return null; // dedup backstop: already processed

                default:
                    // cancelled/expired branches (refund / guarded resurrect)
                    // land with the refund pipeline milestone; record the
                    // intent so money is never untraceable.
                    $payment->stripe_payment_intent_id = $checkout['payment_intent'];
                    $payment->status = PaymentStatus::Succeeded;
                    $payment->save();

                    Log::warning('payment completed for a non-pending booking', [
                        'booking_id' => $booking->id,
                        'status' => $booking->status->value,
                    ]);

                    return null;
            }
        }, attempts: 3);

        if ($confirmedBookingId !== null) {
            event(new BookingConfirmed($confirmedBookingId));
        }
    }
}
