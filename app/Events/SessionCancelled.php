<?php

declare(strict_types=1);

namespace App\Events;

final readonly class SessionCancelled
{
    /** @param list<int> $cancelledBookingIds captured before the cascade commits */
    public function __construct(
        public int $sessionId,
        public array $cancelledBookingIds,
    ) {}
}
