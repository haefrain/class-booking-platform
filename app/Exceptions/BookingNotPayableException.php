<?php

declare(strict_types=1);

namespace App\Exceptions;

class BookingNotPayableException extends DomainException
{
    public function __construct(string $message = 'This booking has no pending payment.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'booking_not_payable';
    }
}
