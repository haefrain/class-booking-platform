# ADR 0004 — Generated columns as MySQL partial unique indexes

## Status
Accepted

## Context
"At most one ACTIVE booking per user/session, unlimited history rows" wants a
partial unique index — which MySQL does not have.

## Decision
A STORED generated column `active` is `1` for live states and `NULL`
otherwise; since NULLs are distinct in unique indexes,
`UNIQUE (class_session_id, user_id, active)` enforces exactly-one-live with
unlimited cancelled/expired history. Same trick on `waitlist_entries` for
waiting rows, plus `CHECK (booked_count <= capacity)` on sessions.

## Consequences
- Invariants I1/I3/I4 hold even against raw SQL — the concurrency suite
  fires them on purpose without the lock.
- The columns are documented as MySQL-specific by design (fixed decision:
  MySQL 8); the suite is mysql-only and says so.
