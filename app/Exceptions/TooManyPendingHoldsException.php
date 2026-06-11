<?php

declare(strict_types=1);

namespace App\Exceptions;

class TooManyPendingHoldsException extends DomainException
{
    public function __construct(string $message = 'You have too many unpaid bookings on hold.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'too_many_pending_holds';
    }
}
