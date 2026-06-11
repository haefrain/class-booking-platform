<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    // "completed" is deliberately NOT a state: it is derived from
    // starts_at < now, so no job ever has to flip finished sessions.
}
