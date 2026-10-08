<?php

declare(strict_types=1);

namespace App\Http\Requests\Jobs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:200'],
            'location' => ['sometimes', 'string', 'max:160'],
            'company' => ['sometimes', 'string', 'max:160'],
            'work_modes' => ['sometimes', 'array'],
            'work_modes.*' => [Rule::in(['onsite', 'hybrid', 'remote'])],
            'employment_types' => ['sometimes', 'array'],
            'employment_types.*' => [Rule::in(['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'])],
            'seniorities' => ['sometimes', 'array'],
            'seniorities.*' => [Rule::in(['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])],
            'salary_min' => ['sometimes', 'integer', 'min:0'],
            'salary_max' => ['sometimes', 'integer', 'min:0'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'industry_id' => ['sometimes', 'integer', 'exists:industries,id'],
            'job_category_id' => ['sometimes', 'integer', 'exists:job_categories,id'],
            'job_role_id' => ['sometimes', 'integer', 'exists:job_roles,id'],
            'education_level_id' => ['sometimes', 'integer', 'exists:education_levels,id'],
            'skill_ids' => ['sometimes', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'posted_within_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'deadline_before' => ['sometimes', 'date'],
            'sort' => ['sometimes', Rule::in(['relevance', 'newest', 'salary_desc', 'deadline'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
