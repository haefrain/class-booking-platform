<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\GatewayException;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RefundPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $paymentId,
    ) {}

    public function handle(PaymentGateway $gateway): void
    {
        // ATOMIC claim (M2): double dispatch (booking-cancel + session-cancel
        // both firing) results in exactly one gateway call — the affected-rows
        // guard is the first line, the Stripe idempotency key the second.
        $claimed = DB::table('payments')
            ->where('id', $this->paymentId)
            ->where('status', PaymentStatus::Succeeded->value)
            ->update([
                'status' => PaymentStatus::RefundPending->value,
                'refund_requested_at' => CarbonImmutable::now(),
            ]);

        if ($claimed !== 1) {
            return; // someone else owns the refund, or nothing to refund
        }

        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($this->paymentId);

        if ($payment->stripe_payment_intent_id === null) {
            // Money was never captured under an intent we know: surface it.
            $payment->status = PaymentStatus::RefundFailed;
            $payment->save();

            return;
        }

        try {
            $refund = $gateway->refund(
                $payment->stripe_payment_intent_id,
                "refund-booking-{$payment->booking_id}",
            );
        } catch (GatewayException $e) {
            report($e);
            $payment->status = PaymentStatus::RefundFailed; // admin retry button
            $payment->save();

            return;
        }

        // charge.refunded confirms amounts; we record the reference now.
        $payment->stripe_refund_id = $refund['id'];
        $payment->save();
    }
}
