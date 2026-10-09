<?php

declare(strict_types=1);

namespace App\Http\Requests\Matching;

use Illuminate\Foundation\Http\FormRequest;

class IndexMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'limit' => $this->query('limit', 20),
        ]);
    }
}
