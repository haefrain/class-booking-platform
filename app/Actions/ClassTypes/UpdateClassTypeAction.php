<?php

declare(strict_types=1);

namespace App\Actions\ClassTypes;

use App\Models\ClassType;
use Illuminate\Support\Arr;

class UpdateClassTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes  validated Update payload
     */
    public function handle(ClassType $type, array $attributes): ClassType
    {
        // Slug is immutable after creation (catalog URLs may reference it).
        $type->fill(Arr::only($attributes, [
            'name', 'description', 'duration_minutes', 'default_capacity',
            'cancellation_deadline_hours', 'is_active',
        ]));

        if (array_key_exists('price_cents', $attributes)) {
            // Explicit money write; future sessions/bookings snapshot prices,
            // so edits never rewrite sold seats.
            $type->price_cents = (int) $attributes['price_cents'];
        }

        $type->save();

        return $type->refresh();
    }
}
