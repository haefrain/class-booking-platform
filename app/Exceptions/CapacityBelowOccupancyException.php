<?php

declare(strict_types=1);

namespace App\Exceptions;

class CapacityBelowOccupancyException extends DomainException
{
    public function __construct(string $message = 'Capacity cannot drop below the current number of booked seats.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'capacity_below_occupancy';
    }
}
