<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Academy identity & timezone
    |--------------------------------------------------------------------------
    |
    | Single-academy deployment. All recurrence rules (schedules) are defined
    | in this local timezone and expanded to UTC sessions; the database only
    | ever stores UTC. Changing the timezone mid-life requires regenerating
    | future sessions (see runbook in README).
    |
    */

    'name' => env('ACADEMY_NAME', 'Andina Yoga Studio'),

    'timezone' => env('ACADEMY_TIMEZONE', 'America/Bogota'),

];
