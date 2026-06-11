<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\BookingStatus;

class InvalidBookingTransitionException extends DomainException
{
    public function __construct(
        private readonly BookingStatus $from,
        private readonly BookingStatus $to,
    ) {
        parent::__construct(sprintf('A %s booking cannot become %s.', $from->value, $to->value));
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'invalid_booking_transition';
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
        ];
    }
}
