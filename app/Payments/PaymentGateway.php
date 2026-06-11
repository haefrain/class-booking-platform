<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Hexagonal boundary: Stripe lives behind this port, exclusively in
 * post-commit code (request tails, queued jobs) — NEVER inside a database
 * transaction. Tests swap in the in-memory fake.
 */
interface PaymentGateway
{
    /**
     * @param  array{booking_id: int, user_id: int, amount_cents: int, currency: string, name: string, success_url: string, cancel_url: string, expires_at: int, idempotency_key: string}  $payload
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(array $payload): array;

    /**
     * @throws CheckoutAlreadyCompletedException when the session was paid
     * @throws GatewayException on any other gateway failure
     */
    public function expireCheckoutSession(string $checkoutSessionId): void;

    /**
     * @return array{id: string, status: string, payment_status: string, amount_total: int|null, currency: string|null, payment_intent: string|null, url: string|null}
     */
    public function retrieveCheckoutSession(string $checkoutSessionId): array;

    /**
     * @return array{id: string} the refund id
     *
     * @throws GatewayException
     */
    public function refund(string $paymentIntentId, string $idempotencyKey): array;

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}
     *
     * @throws InvalidWebhookSignatureException
     */
    public function parseWebhookEvent(string $payload, string $signature): array;
}
