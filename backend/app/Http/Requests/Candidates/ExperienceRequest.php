<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExperienceRequest extends FormRequest
{
    public function rules(): array
    {
        $store = $this->isMethod('POST');

        return [
            'job_title' => [$store ? 'required' : 'sometimes', 'string', 'max:160'],
            'company' => [$store ? 'required' : 'sometimes', 'string', 'max:160'],
            'employment_type' => ['nullable', Rule::in(UpdateProfileRequest::employmentTypes())],
            'location' => ['nullable', 'string', 'max:160'],
            'start_date' => [$store ? 'required' : 'sometimes', 'date', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:10000'],
            'achievements' => ['nullable', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
