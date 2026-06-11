<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\SessionCancelled;
use App\Events\WaitlistPromoted;
use App\Models\Booking;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\SessionCancelledNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * One queued mail fan-out for the whole booking lifecycle. AfterCommit: a
 * rolled-back transaction never emails anyone.
 */
class SendBookingLifecycleMail implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $backoff = 10;

    public function handle(BookingConfirmed|BookingCancelled|WaitlistPromoted|SessionCancelled $event): void
    {
        match ($event::class) {
            BookingConfirmed::class => $this->notify($event->bookingId, BookingConfirmedNotification::class),
            BookingCancelled::class => $this->notify($event->bookingId, BookingCancelledNotification::class),
            WaitlistPromoted::class => $this->notify($event->bookingId, WaitlistPromotedNotification::class),
            SessionCancelled::class => collect($event->cancelledBookingIds)
                ->each(fn (int $id) => $this->notify($id, SessionCancelledNotification::class)),
        };
    }

    /**
     * @param  class-string  $notification
     */
    private function notify(int $bookingId, string $notification): void
    {
        $booking = Booking::query()->with(['user', 'session.classType'])->find($bookingId);

        if ($booking === null) {
            return;
        }

        $booking->user?->notify(new $notification($booking));
    }
}
