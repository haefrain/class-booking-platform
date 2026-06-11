<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessStripeWebhookJob;
use App\Models\StripeEvent;
use App\Payments\InvalidWebhookSignatureException;
use App\Payments\PaymentGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    /**
     * Verify, ledger, ack fast: processing happens on the queue so Stripe
     * never times out on our business logic.
     */
    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        try {
            $event = $gateway->parseWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (InvalidWebhookSignatureException) {
            return response('invalid signature', 400);
        }

        try {
            $row = StripeEvent::create([
                'stripe_event_id' => $event['id'],
                'type' => $event['type'],
                'payload' => $event['data'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('duplicate', 200); // replay → already ledgered
        }

        ProcessStripeWebhookJob::dispatch($row->id)->onQueue('critical');

        return response('ok', 200);
    }
}
