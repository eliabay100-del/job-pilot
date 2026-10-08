<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;

class EducationRequest extends FormRequest
{
    public function rules(): array
    {
        $store = $this->isMethod('POST');

        return [
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'institution_name' => [$store ? 'required_without:institution_id' : 'sometimes', 'nullable', 'string', 'max:255'],
            'degree' => [$store ? 'required' : 'sometimes', 'string', 'max:160'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'field_of_study' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'currently_studying' => ['sometimes', 'boolean'],
            'start_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:2100', 'gte:start_year'],
            'cgpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'grade' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'array'],
            'achievements.*' => ['string', 'max:500'],
        ];
    }
}
