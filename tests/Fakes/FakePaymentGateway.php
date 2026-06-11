<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Payments\CheckoutAlreadyCompletedException;
use App\Payments\GatewayException;
use App\Payments\PaymentGateway;

/**
 * In-memory Stripe stand-in: deterministic ids, scriptable completions and
 * failures, call recording for behavioural assertions.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, array{payload: array<string, mixed>, status: string, payment_status: string, amount_total: int|null, currency: string|null, payment_intent: string|null}> */
    public array $sessions = [];

    /** @var list<string> */
    public array $expireCalls = [];

    public ?string $failNextWith = null;

    private int $sequence = 0;

    public function createCheckoutSession(array $payload): array
    {
        $this->maybeFail();

        $id = 'cs_fake_'.++$this->sequence;
        $this->sessions[$id] = [
            'payload' => $payload,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => $payload['amount_cents'],
            'currency' => $payload['currency'],
            'payment_intent' => null,
        ];

        return ['id' => $id, 'url' => "https://checkout.fake/{$id}"];
    }

    public function expireCheckoutSession(string $checkoutSessionId): void
    {
        $this->maybeFail();
        $this->expireCalls[] = $checkoutSessionId;

        $session = $this->sessions[$checkoutSessionId] ?? null;

        if ($session !== null && $session['status'] === 'complete') {
            throw new CheckoutAlreadyCompletedException('You cannot expire a session that is already complete.');
        }

        if ($session !== null) {
            $this->sessions[$checkoutSessionId]['status'] = 'expired';
        }
    }

    public function retrieveCheckoutSession(string $checkoutSessionId): array
    {
        $this->maybeFail();

        $session = $this->sessions[$checkoutSessionId]
            ?? throw new GatewayException("No such checkout session {$checkoutSessionId}");

        return [
            'id' => $checkoutSessionId,
            'status' => $session['status'],
            'payment_status' => $session['payment_status'],
            'amount_total' => $session['amount_total'],
            'currency' => $session['currency'],
            'payment_intent' => $session['payment_intent'],
            'url' => "https://checkout.fake/{$checkoutSessionId}",
        ];
    }

    /** @var list<array{payment_intent: string, idempotency_key: string}> */
    public array $refundCalls = [];

    public function refund(string $paymentIntentId, string $idempotencyKey): array
    {
        $this->maybeFail();
        $this->refundCalls[] = ['payment_intent' => $paymentIntentId, 'idempotency_key' => $idempotencyKey];

        return ['id' => 're_fake_'.count($this->refundCalls)];
    }

    public function parseWebhookEvent(string $payload, string $signature): array
    {
        // Signature verification is exercised against the REAL StripeGateway
        // in its own HMAC test; the fake trusts its caller.
        /** @var array{id: string, type: string, data: array<string, mixed>} */
        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Test control: mark a checkout as paid, as Stripe would after 4242…. */
    public function completeCheckout(string $checkoutSessionId, ?int $amountTotal = null, ?string $paymentIntent = null): void
    {
        $session = &$this->sessions[$checkoutSessionId];
        $session['status'] = 'complete';
        $session['payment_status'] = 'paid';
        $session['payment_intent'] = $paymentIntent ?? 'pi_fake_'.$checkoutSessionId;
        if ($amountTotal !== null) {
            $session['amount_total'] = $amountTotal;
        }
    }

    public function failNextWith(string $message): void
    {
        $this->failNextWith = $message;
    }

    private function maybeFail(): void
    {
        if ($this->failNextWith !== null) {
            $message = $this->failNextWith;
            $this->failNextWith = null;

            throw new GatewayException($message);
        }
    }
}
