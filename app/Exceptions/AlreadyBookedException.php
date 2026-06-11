<?php

declare(strict_types=1);

namespace App\Exceptions;

class AlreadyBookedException extends DomainException
{
    public function __construct(string $message = 'You already hold a booking for this session.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'already_booked';
    }
}
