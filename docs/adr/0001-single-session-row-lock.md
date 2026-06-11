# ADR 0001 — One serialization point: the session row lock

## Status
Accepted

## Context
Overbooking is the defining failure of a booking engine. Options: optimistic
retries on a version column, queue-serialized writes per session, or a
pessimistic row lock.

## Decision
Every seat mutation runs `DB::transaction` → `lockForUpdate()` on the
`class_sessions` row, re-fetching under the lock (route-bound instances are
never trusted). Lock ordering is fixed — session first, then booking/waitlist
rows — so deadlocks cannot form. Booking is not a pure increment (guards +
insert + counter + promotion under one consistent view), which rules out
atomic-UPDATE tricks. Lock contention is irrelevant at studio scale, and we
say so honestly instead of building for imaginary load.

## Consequences
- The whole engine reasons about ONE invariant boundary; the concurrency
  suite proves the lock with a second MySQL connection and `FOR UPDATE
  NOWAIT` (error 3572), not with mocks.
- DB constraints (CHECK, generated-column uniques) remain as backstops, so
  even a future code path that skips the lock cannot corrupt seats.
- No network I/O ever runs inside the transaction: Stripe lives in request
  tails and queued jobs exclusively.
