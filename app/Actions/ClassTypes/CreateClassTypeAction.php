<?php

declare(strict_types=1);

namespace App\Actions\ClassTypes;

use App\Models\ClassType;
use Illuminate\Support\Str;

class CreateClassTypeAction
{
    /**
     * @param  array<string, mixed>  $attributes  validated Store payload
     */
    public function handle(array $attributes): ClassType
    {
        $type = new ClassType([
            'name' => (string) $attributes['name'],
            'slug' => $this->uniqueSlug((string) $attributes['name']),
            'description' => isset($attributes['description']) ? (string) $attributes['description'] : null,
            'duration_minutes' => (int) $attributes['duration_minutes'],
            'default_capacity' => (int) $attributes['default_capacity'],
            'cancellation_deadline_hours' => (int) $attributes['cancellation_deadline_hours'],
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);

        // Money is never mass-assigned: explicit write from validated input.
        $type->price_cents = (int) $attributes['price_cents'];
        $type->save();

        return $type;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'class' : $base;

        $slug = $base;
        while (ClassType::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
