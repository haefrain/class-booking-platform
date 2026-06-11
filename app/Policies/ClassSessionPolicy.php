<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\User;

class ClassSessionPolicy
{
    /** Roster (attendees + waitlist): admin any, instructor own only. */
    public function viewRoster(User $user, ClassSession $session): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isInstructor() && $session->instructor_id === $user->getKey();
    }

    public function cancel(User $user, ClassSession $session): bool
    {
        return $user->isAdmin();
    }

    public function updateCapacity(User $user, ClassSession $session): bool
    {
        return $user->isAdmin();
    }
}
