<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    case RefundFailed = 'refund_failed';
}
