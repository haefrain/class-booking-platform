<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Enums\BookingStatus;
use App\Enums\CancellationKind;
use App\Enums\SessionStatus;
use App\Enums\WaitlistStatus;
use App\Events\SessionCancelled;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CancelSessionAction
{
    public function handle(ClassSession $session, User $actor, string $reason): ClassSession
    {
        /** @var list<int> $cancelledBookingIds */
        $cancelledBookingIds = [];

        $cancelled = DB::transaction(function () use ($session, $actor, $reason, &$cancelledBookingIds): ClassSession {
            /** @var ClassSession $fresh */
            $fresh = ClassSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $fresh->bookings()
                ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
                ->lockForUpdate()
                ->get()
                ->each(function (Booking $booking) use ($actor, &$cancelledBookingIds): void {
                    $booking->status = BookingStatus::Cancelled;
                    $booking->cancelled_at = CarbonImmutable::now();
                    $booking->cancelled_by = $actor->getKey();
                    $booking->cancellation_kind = CancellationKind::SessionCancelled;
                    $booking->payment_deadline_at = null; // I7
                    $booking->save();
                    $cancelledBookingIds[] = $booking->id;
                });

            $fresh->waitlistEntries()
                ->where('status', WaitlistStatus::Waiting)
                ->get()
                ->each(function (WaitlistEntry $entry): void {
                    $entry->status = WaitlistStatus::Expired;
                    $entry->save();
                });

            $fresh->booked_count = 0;
            $fresh->status = SessionStatus::Cancelled;
            $fresh->cancelled_at = CarbonImmutable::now();
            $fresh->cancelled_by = $actor->getKey();
            $fresh->cancellation_reason = $reason;
            $fresh->save();

            return $fresh;
        }, attempts: 3);

        event(new SessionCancelled($cancelled->id, $cancelledBookingIds));

        return $cancelled;
    }
}
