# ADR 0002 — Waitlist promotion inside the seat-releasing transaction

## Status
Accepted

## Context
When a seat frees (cancel/expiry/capacity-grow), the next waiter should get
it. A queued promotion (events + worker) opens a window where a direct booker
sees the free seat first and starves the queue.

## Decision
`PromoteNextWaiterAction::withinLockedSession()` runs in the SAME transaction
that frees the seat, under the lock the caller already holds. Decrement and
re-increment commit atomically: the seat is never observably free while a
waiter exists (invariant I6 — auditable at every commit point). For paid
classes inside the 30-minute minimum offer window, the queue is expired
instead — nobody can be served a payable offer, so the seat becomes genuinely
free and I6 holds exactly.

## Consequences
- A racing direct booker serializes behind the lock and finds the session
  full — the lock is the tiebreak, proven in the concurrency suite.
- Notifications still dispatch after commit; only the seat math is
  synchronous.
- FIFO is `ORDER BY id` — no position column to maintain, ever.
