# ADR 0006 — Weekly slots expanded over a rolling horizon (no RRULE)

## Status
Accepted

## Context
Studios run fixed weekly schedules. Full RRULE engines (exdates, monthly
patterns, count limits) are the classic over-engineering trap here.

## Decision
A schedule is one weekly slot (`weekday + start_time`) in ACADEMY-LOCAL wall
time. A generator expands it into concrete UTC sessions over a rolling
56-day horizon, `insertOrIgnore` keyed on `UNIQUE(schedule_id, local_date)` —
the local date is stable across DST while the UTC instant shifts (pinned
tests on Madrid 2026 transitions). Existing rows are NEVER updated: edits
require an explicit regeneration that refuses to touch sessions holding live
bookings. Ad-hoc one-off sessions and per-session instructor substitution
are documented extension points, deliberately cut.

## Consequences
- The horizon doubles as the booking window — a feature, documented.
- DST correctness is a property of the key choice, not of careful math
  sprinkled around the codebase.
- The generator is shared verbatim by the scheduler, the admin UI and the
  seeders: one code path to trust.
