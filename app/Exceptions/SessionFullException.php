<?php

declare(strict_types=1);

namespace App\Exceptions;

class SessionFullException extends DomainException
{
    public function __construct(string $message = 'This session is full — you can join the waitlist.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'session_full';
    }
}
