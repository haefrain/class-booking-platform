<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';

    /** The landing route for each role after login. */
    public function homePath(): string
    {
        return match ($this) {
            self::Admin => '/admin',
            self::Instructor => '/instructor/sessions',
            self::Student => '/catalog',
        };
    }
}
