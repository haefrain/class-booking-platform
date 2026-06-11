<?php

declare(strict_types=1);

namespace App\Events;

final readonly class WaitlistPromoted
{
    public function __construct(
        public int $entryId,
        public int $bookingId,
    ) {}
}
