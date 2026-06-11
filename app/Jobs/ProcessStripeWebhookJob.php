<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payments\ConfirmPaidBookingAction;
use App\Actions\Payments\ExpirePendingBookingAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StripeEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $stripeEventRowId,
    ) {}

    public function handle(
        ConfirmPaidBookingAction $confirm,
        ExpirePendingBookingAction $expire,
    ): void {
        $row = StripeEvent::query()->find($this->stripeEventRowId);

        if ($row === null || $row->processed_at !== null) {
            return;
        }

        $checkoutId = (string) ($row->payload['id'] ?? '');

        switch ($row->type) {
            case 'checkout.session.completed':
                $payment = Payment::query()->where('stripe_checkout_session_id', $checkoutId)->first();
                if ($payment === null) {
                    // Event raced ahead of our local payments row: retry later.
                    $this->release(10);

                    return;
                }

                $confirm->handle([
                    'id' => $checkoutId,
                    'amount_total' => isset($row->payload['amount_total']) ? (int) $row->payload['amount_total'] : null,
                    'currency' => isset($row->payload['currency']) ? (string) $row->payload['currency'] : null,
                    'payment_intent' => isset($row->payload['payment_intent']) ? (string) $row->payload['payment_intent'] : null,
                ]);
                break;

            case 'checkout.session.expired':
                $bookingId = Payment::query()
                    ->where('stripe_checkout_session_id', $checkoutId)
                    ->where('status', PaymentStatus::Pending)
                    ->value('booking_id');

                if ($bookingId !== null) {
                    $expire->handle((int) $bookingId);
                }
                break;

            default:
                // Unhandled types (charge.refunded lands with the refund
                // milestone) are ledgered and marked processed.
                break;
        }

        $row->processed_at = CarbonImmutable::now();
        $row->save();
    }
}
