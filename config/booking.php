<?php

declare(strict_types=1);

return [

    // Rolling generation horizon: sessions exist (and are bookable) this many
    // days ahead. The scheduler keeps the window topped up; it doubles as the
    // booking window (documented as a feature).
    'horizon_days' => (int) env('BOOKING_HORIZON_DAYS', 56),

    // How long a paid booking may sit in pending_payment before the sweep
    // releases its seat. Must be >= Stripe Checkout's 30-minute expires_at
    // floor for direct bookings (waitlist offers clamp — blueprint D8).
    'pending_payment_ttl_minutes' => (int) env('BOOKING_PENDING_TTL', 30),

    // Waitlist promotion offer lifetime (capped at the session start).
    'waitlist_offer_ttl_minutes' => (int) env('BOOKING_OFFER_TTL', 360),

    // Below this window before start, paid waitlist offers cannot be served
    // (Stripe's 30-min floor) — entries expire instead (blueprint H2).
    'waitlist_min_offer_window_minutes' => (int) env('BOOKING_MIN_OFFER_WINDOW', 30),

    // Anti-griefing cap: max simultaneous pending_payment holds per user
    // across all sessions (blueprint D16).
    'max_concurrent_pending_per_user' => (int) env('BOOKING_MAX_PENDING_PER_USER', 3),

    // Reminder lead time (hourly command scans a 23h–25h window).
    'reminder_lead_hours' => (int) env('BOOKING_REMINDER_LEAD_HOURS', 24),

];
