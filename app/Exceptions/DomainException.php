<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for business-rule violations. Carries its own HTTP status, machine
 * code and details so the central exception mapping renders every domain
 * rule consistently — actions and controllers never set HTTP codes.
 */
abstract class DomainException extends RuntimeException
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [];
    }
}
