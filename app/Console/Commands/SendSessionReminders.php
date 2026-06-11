<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Jobs\SendSessionReminderJob;
use App\Models\Booking;
use Illuminate\Console\Command;

/**
 * Hourly sweep over a DELIBERATELY overlapping window (23h–25h before
 * start): a missed tick is healed by the next one, and the per-booking
 * reminder_sent_at claim guarantees at-most-once anyway. All math is in UTC
 * instants, so DST transitions cannot shift anyone's reminder.
 */
class SendSessionReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Queue T-24h reminders for confirmed bookings';

    public function handle(): int
    {
        $lead = (int) config('booking.reminder_lead_hours');

        $due = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereHas('session', fn ($query) => $query
                ->where('status', SessionStatus::Scheduled)
                ->whereBetween('starts_at', [
                    now()->addHours($lead - 1),
                    now()->addHours($lead + 1),
                ]))
            ->pluck('id');

        foreach ($due as $bookingId) {
            SendSessionReminderJob::dispatch((int) $bookingId)->onQueue('mail');
        }

        $this->info("Queued {$due->count()} reminder(s).");

        return self::SUCCESS;
    }
}
