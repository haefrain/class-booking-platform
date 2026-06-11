<?php

declare(strict_types=1);

namespace App\Events;

final readonly class BookingExpired
{
    public function __construct(public int $bookingId) {}
}
