<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class IndexCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public page.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'week' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
