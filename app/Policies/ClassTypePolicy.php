<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClassType;
use App\Models\User;

/**
 * Second authorization layer behind the role:admin section middleware.
 */
class ClassTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClassType $type): bool
    {
        return $user->isAdmin();
    }
}
