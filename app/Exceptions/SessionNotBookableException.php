<?php

declare(strict_types=1);

namespace App\Exceptions;

class SessionNotBookableException extends DomainException
{
    public function __construct(string $message = 'This session can no longer be booked.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'session_not_bookable';
    }
}
