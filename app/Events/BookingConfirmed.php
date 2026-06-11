<?php

declare(strict_types=1);

namespace App\Events;

/** Carries ids only: queued listeners re-fetch fresh state. */
final readonly class BookingConfirmed
{
    public function __construct(public int $bookingId) {}
}
