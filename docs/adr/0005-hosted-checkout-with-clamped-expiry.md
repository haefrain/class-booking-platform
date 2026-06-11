# ADR 0005 — Hosted Stripe Checkout with a clamped expires_at

## Status
Accepted

## Context
One-off class payments need a checkout and one refund path — no
subscriptions. Stripe Checkout enforces a hard 30-minute floor on
`expires_at`, but late waitlist offers can have shorter local deadlines.

## Decision
Hosted Checkout behind a `PaymentGateway` port (SAQ-A scope, fakeable in CI).
`expires_at = max(local deadline, now+30min)`: the clamp satisfies Stripe,
and the LOCAL deadline stays authoritative via the sweep, which kills the
checkout early when the clamp pushed Stripe past it. Idempotency keys are
attempt-versioned (`checkout-booking-{id}-{n}`) because re-creation with a
different `expires_at` would otherwise trip Stripe's
same-key-different-params error. Payment-after-cancel always refunds and
never resurrects; payment-after-expiry resurrects only behind four guards.

## Consequences
- "Money is never silently kept" is enforced by construction: every
  non-confirming branch that captured money dispatches the refund job, whose
  atomic claim makes double dispatch harmless.
- Out-of-band refunds (partial/dashboard) flag the payment for the admin and
  never auto-cancel a seat.
