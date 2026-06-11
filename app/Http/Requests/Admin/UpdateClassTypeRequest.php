<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is the ClassTypePolicy's job (controller authorize call).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'default_capacity' => ['required', 'integer', 'between:1,200'],
            'price_cents' => ['required', 'integer', 'between:0,100000'],
            'cancellation_deadline_hours' => ['required', 'integer', 'between:0,168'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
