<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;

class WaitlistEntryPolicy
{
    public function create(User $user, ClassSession $session): bool
    {
        return $user->isStudent();
    }

    public function delete(User $user, WaitlistEntry $entry): bool
    {
        return $user->isAdmin() || $entry->user_id === $user->getKey();
    }
}
