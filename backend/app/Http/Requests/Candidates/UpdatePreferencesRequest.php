<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'work_modes' => ['sometimes', 'array'],
            'work_modes.*' => [Rule::in(['onsite', 'hybrid', 'remote'])],
            'locations' => ['sometimes', 'array', 'max:20'],
            'locations.*' => ['string', 'max:160'],
            'employment_types' => ['sometimes', 'array'],
            'employment_types.*' => [Rule::in(UpdateProfileRequest::employmentTypes())],
            'industries' => ['sometimes', 'array', 'max:20'],
            'industries.*' => ['string', 'max:160'],
            'job_category_ids' => ['sometimes', 'array'],
            'job_category_ids.*' => ['integer', 'exists:job_categories,id'],
            'desired_job_role_ids' => ['sometimes', 'array'],
            'desired_job_role_ids.*' => ['integer', 'exists:job_roles,id'],
            'desired_industry_ids' => ['sometimes', 'array'],
            'desired_industry_ids.*' => ['integer', 'exists:industries,id'],
            'salary_min_monthly_eth' => ['nullable', 'integer', 'min:0'],
            'experience_level' => ['nullable', 'string', 'max:32'],
            'open_to_remote' => ['sometimes', 'boolean'],
            'willing_to_relocate' => ['sometimes', 'boolean'],
        ];
    }
}
