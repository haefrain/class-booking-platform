<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * cancelled is terminal: a payment landing after cancellation is always
     * refunded, never resurrected. expired → confirmed is the ONE sanctioned
     * resurrection (fully guarded in ConfirmPaidBookingAction).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Expired, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Expired => [self::Confirmed],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return $this === self::PendingPayment || $this === self::Confirmed;
    }
}
