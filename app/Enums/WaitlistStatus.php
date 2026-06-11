<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Promoted = 'promoted';
    case Left = 'left';
    case Expired = 'expired';
}
