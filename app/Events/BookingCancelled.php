<?php

declare(strict_types=1);

namespace App\Events;

final readonly class BookingCancelled
{
    public function __construct(public int $bookingId) {}
}
