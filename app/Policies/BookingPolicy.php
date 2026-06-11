<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;

class BookingPolicy
{
    /** Students book for themselves; staff never book (blueprint §6). */
    public function create(User $user, ClassSession $session): bool
    {
        return $user->isStudent();
    }

    /**
     * Sequential ids make every booking-scoped GET a probe target: owner or
     * admin only (H4).
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $booking->user_id === $user->getKey();
    }

    /** Own pending holds only — the action re-guards pending_payment. */
    public function pay(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->getKey();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $booking->user_id === $user->getKey();
    }
}
