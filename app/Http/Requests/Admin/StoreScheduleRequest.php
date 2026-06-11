<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is the SchedulePolicy's job (controller authorize call).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_type_id' => ['required', 'integer', 'exists:class_types,id'],
            'instructor_id' => ['required', 'integer', $this->instructorRule()],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'between:15,240'],
            'capacity' => ['nullable', 'integer', 'between:1,200'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function instructorRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = is_numeric($value) ? User::query()->find((int) $value) : null;

            if ($user === null || ! $user->isInstructor()) {
                $fail('The selected :attribute must be an instructor.');
            }
        };
    }
}
