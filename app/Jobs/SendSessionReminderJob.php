<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\SessionReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSessionReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $bookingId,
    ) {}

    public function handle(): void
    {
        // Claim-THEN-send (at-most-once): a duplicate dispatch or an
        // overlapping sweep window can never double-spam; a crash after the
        // claim loses one reminder, which beats the alternative.
        $claimed = DB::table('bookings')
            ->where('id', $this->bookingId)
            ->whereNull('reminder_sent_at')
            ->where('status', BookingStatus::Confirmed->value)
            ->update(['reminder_sent_at' => CarbonImmutable::now()]);

        if ($claimed !== 1) {
            return;
        }

        $booking = Booking::query()
            ->with(['user', 'session.classType'])
            ->find($this->bookingId);

        $booking?->user?->notify(new SessionReminderNotification($booking));
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('session reminder failed permanently', [
            'booking_id' => $this->bookingId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
