<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

class UpdateScheduleRequest extends StoreScheduleRequest
{
    // Same shape as creation; edits never rewrite generated sessions —
    // regeneration is a separate explicit endpoint.
}
