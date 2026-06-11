<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Exceptions\AlreadyBookedException;
use App\Exceptions\AlreadyWaitingException;
use App\Exceptions\SeatAvailableException;
use App\Exceptions\SessionNotBookableException;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class JoinWaitlistAction
{
    public function handle(User $user, int $sessionId): WaitlistEntry
    {
        return DB::transaction(function () use ($user, $sessionId): WaitlistEntry {
            /** @var ClassSession $session */
            $session = ClassSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->isCancelled() || $session->hasStarted()) {
                throw new SessionNotBookableException;
            }

            if ($session->booked_count < $session->capacity) {
                // Waiting while a seat is free would break I6 by construction.
                throw new SeatAvailableException;
            }

            $hasActiveBooking = $session->bookings()
                ->where('user_id', $user->getKey())
                ->whereIn('status', [BookingStatus::PendingPayment, BookingStatus::Confirmed])
                ->exists();

            if ($hasActiveBooking) {
                throw new AlreadyBookedException('You already hold a seat for this session.');
            }

            $alreadyWaiting = $session->waitlistEntries()
                ->where('user_id', $user->getKey())
                ->where('status', WaitlistStatus::Waiting)
                ->exists();

            if ($alreadyWaiting) {
                throw new AlreadyWaitingException; // unique index is the backstop
            }

            $entry = new WaitlistEntry;
            $entry->class_session_id = $session->getKey();
            $entry->user_id = $user->getKey();
            $entry->status = WaitlistStatus::Waiting;
            $entry->save();

            return $entry;
        }, attempts: 3);
    }
}
