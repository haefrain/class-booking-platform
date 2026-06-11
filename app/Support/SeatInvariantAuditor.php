<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Detection layer of the seat-integrity defence (actions under lock are the
 * first line, DB constraints the backstop). Wired into afterEach for the
 * booking/concurrency suites and exposed via the integrity:check command.
 */
class SeatInvariantAuditor
{
    /** @throws RuntimeException on the first violated invariant */
    public static function assertAll(): void
    {
        $violations = self::violations();

        if ($violations !== []) {
            throw new RuntimeException('Seat invariants violated: '.implode(' | ', $violations));
        }
    }

    /**
     * @return list<string>
     */
    public static function violations(): array
    {
        $violations = [];

        // I1: booked_count <= capacity, always.
        $oversold = DB::table('class_sessions')->whereColumn('booked_count', '>', 'capacity')->count();
        if ($oversold > 0) {
            $violations[] = "I1: {$oversold} session(s) oversold";
        }

        // I2: booked_count equals the number of live bookings — no drift, ever.
        $drift = DB::table('class_sessions as s')
            ->leftJoin('bookings as b', function ($join): void {
                $join->on('b.class_session_id', '=', 's.id')
                    ->whereIn('b.status', ['pending_payment', 'confirmed']);
            })
            ->groupBy('s.id', 's.booked_count')
            ->havingRaw('s.booked_count <> COUNT(b.id)')
            ->count(DB::raw('1'));
        if ($drift > 0) {
            $violations[] = "I2: booked_count drift on {$drift} session(s)";
        }

        // I4: never an active booking AND a waiting entry for one session.
        $both = DB::table('bookings as b')
            ->join('waitlist_entries as w', function ($join): void {
                $join->on('w.class_session_id', '=', 'b.class_session_id')
                    ->on('w.user_id', '=', 'b.user_id')
                    ->where('w.status', 'waiting');
            })
            ->whereIn('b.status', ['pending_payment', 'confirmed'])
            ->count();
        if ($both > 0) {
            $violations[] = "I4: {$both} user(s) holding a seat while waiting";
        }

        // I6: a waiter implies a full session at every commit point.
        $leaky = DB::table('class_sessions as s')
            ->whereColumn('s.booked_count', '<', 's.capacity')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('waitlist_entries as w')
                    ->whereColumn('w.class_session_id', 's.id')
                    ->where('w.status', 'waiting');
            })
            ->where('s.status', 'scheduled')
            ->where('s.starts_at', '>', now())
            ->count();
        if ($leaky > 0) {
            $violations[] = "I6: {$leaky} session(s) with free seats AND waiters";
        }

        // I7: payment_deadline_at non-null IFF pending_payment.
        $deadlineDrift = DB::table('bookings')
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('status', 'pending_payment')->whereNull('payment_deadline_at'))
                    ->orWhere(fn ($q) => $q->where('status', '!=', 'pending_payment')->whereNotNull('payment_deadline_at'));
            })
            ->count();
        if ($deadlineDrift > 0) {
            $violations[] = "I7: {$deadlineDrift} booking(s) with deadline/status mismatch";
        }

        return $violations;
    }

    /**
     * Operational alerts (not test invariants): surfaced by integrity:check.
     *
     * @return list<string>
     */
    public static function operationalAlerts(): array
    {
        $alerts = [];

        // A 200-acked webhook whose job exhausted retries would otherwise
        // vanish: Stripe never redelivers (M6).
        $stuck = DB::table('stripe_events')
            ->whereNull('processed_at')
            ->where('created_at', '<', now()->subHour())
            ->count();
        if ($stuck > 0) {
            $alerts[] = "{$stuck} webhook event(s) unprocessed for over an hour";
        }

        $flagged = DB::table('payments')->whereNotNull('flag')->count();
        if ($flagged > 0) {
            $alerts[] = "{$flagged} flagged payment(s) awaiting admin review";
        }

        $failed = DB::table('payments')->where('status', 'refund_failed')->count();
        if ($failed > 0) {
            $alerts[] = "{$failed} refund(s) failed — retry from the admin payments page";
        }

        return $alerts;
    }
}
