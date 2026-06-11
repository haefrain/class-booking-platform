<?php

declare(strict_types=1);

namespace App\Exceptions;

class IdempotencyKeyConflictException extends DomainException
{
    public function __construct(string $message = 'This booking reference belongs to a different request.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'idempotency_conflict';
    }
}
