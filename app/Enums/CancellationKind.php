<?php

declare(strict_types=1);

namespace App\Enums;

enum CancellationKind: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case Admin = 'admin';
    case SessionCancelled = 'session_cancelled';
}
