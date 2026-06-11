<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Single formatter for human-facing session times (mails, flashes): academy
 * wall clock with an explicit timezone label, so a recipient in any country
 * reads an unambiguous instant.
 */
class AcademyTime
{
    public static function format(CarbonInterface $utcInstant): string
    {
        $timezone = (string) config('academy.timezone');

        return CarbonImmutable::instance($utcInstant)
            ->setTimezone($timezone)
            ->format('D, M j · g:i A')." ({$timezone})";
    }
}
