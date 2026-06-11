<?php

declare(strict_types=1);

namespace App\Exceptions;

class SeatAvailableException extends DomainException
{
    public function __construct(string $message = 'A seat is available — book it directly.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'seat_available';
    }
}
