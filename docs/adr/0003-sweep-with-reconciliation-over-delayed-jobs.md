# ADR 0003 — Expiry sweep with Stripe-first reconciliation

## Status
Accepted

## Context
Pending-payment holds must release on deadline. Per-booking delayed queue
jobs evaporate on Redis loss, drift after restarts, and must re-check state
anyway. Webhooks alone are not reliable delivery.

## Decision
An every-minute idempotent sweep selects overdue holds. Per row, Stripe goes
FIRST: `expireCheckoutSession` guarantees the checkout is dead before the
seat frees. `AlreadyCompleted` triggers reconciliation — fetch the session
and confirm the booking locally — which makes the sweep double as
webhook-loss insurance. Any other gateway error skips the row for the next
tick: never expire locally while the checkout may still be payable. Each row
is isolated in try/catch, so one poison row never blocks the rest.

## Consequences
- The system self-heals after downtime: state-driven, not schedule-driven.
- Money can never be captured for a seat we silently released.
- `checkout.session.expired` webhooks reuse the same idempotent action.
