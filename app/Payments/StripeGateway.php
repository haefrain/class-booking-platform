<?php

declare(strict_types=1);

namespace App\Payments;

use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly string $webhookSecret,
    ) {}

    public function createCheckoutSession(array $payload): array
    {
        try {
            $session = $this->client->checkout->sessions->create(
                [
                    'mode' => 'payment',
                    'client_reference_id' => (string) $payload['booking_id'],
                    'metadata' => [
                        'booking_id' => (string) $payload['booking_id'],
                        'user_id' => (string) $payload['user_id'],
                    ],
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $payload['currency'],
                            'unit_amount' => $payload['amount_cents'],
                            'product_data' => ['name' => $payload['name']],
                        ],
                    ]],
                    'success_url' => $payload['success_url'],
                    'cancel_url' => $payload['cancel_url'],
                    'expires_at' => $payload['expires_at'],
                ],
                ['idempotency_key' => $payload['idempotency_key']],
            );
        } catch (ApiErrorException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }

        return ['id' => $session->id, 'url' => (string) $session->url];
    }

    public function expireCheckoutSession(string $checkoutSessionId): void
    {
        try {
            $this->client->checkout->sessions->expire($checkoutSessionId);
        } catch (InvalidRequestException $e) {
            // Stripe rejects expiring a completed session with this shape.
            if (str_contains($e->getMessage(), 'complete')) {
                throw new CheckoutAlreadyCompletedException($e->getMessage(), previous: $e);
            }

            throw new GatewayException($e->getMessage(), previous: $e);
        } catch (ApiErrorException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }
    }

    public function retrieveCheckoutSession(string $checkoutSessionId): array
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($checkoutSessionId);
        } catch (ApiErrorException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }

        return [
            'id' => $session->id,
            'status' => (string) $session->status,
            'payment_status' => (string) $session->payment_status,
            'amount_total' => $session->amount_total,
            'currency' => $session->currency,
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : null,
            'url' => $session->url,
        ];
    }

    public function refund(string $paymentIntentId, string $idempotencyKey): array
    {
        try {
            $refund = $this->client->refunds->create(
                ['payment_intent' => $paymentIntentId],
                ['idempotency_key' => $idempotencyKey],
            );
        } catch (ApiErrorException $e) {
            throw new GatewayException($e->getMessage(), previous: $e);
        }

        return ['id' => $refund->id];
    }

    public function parseWebhookEvent(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new InvalidWebhookSignatureException($e->getMessage(), previous: $e);
        }

        return [
            'id' => $event->id,
            'type' => $event->type,
            'data' => $event->data->object->toArray(),
        ];
    }
}
