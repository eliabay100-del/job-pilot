<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'undisclosed'])],
            'photo' => ['nullable', 'image', 'max:2048'],
            'current_job_title' => ['nullable', 'string', 'max:160'],
            'current_company' => ['nullable', 'string', 'max:160'],
            'preferred_job_title' => ['nullable', 'string', 'max:160'],
            'preferred_location' => ['nullable', 'string', 'max:160'],
            'remote_preference' => ['nullable', Rule::in(['onsite', 'hybrid', 'remote', 'flexible'])],
            'employment_type_preference' => ['nullable', Rule::in(self::employmentTypes())],
            'expected_salary_monthly_etb' => ['nullable', 'integer', 'min:0'],
            'availability' => ['nullable', 'string', 'max:120'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<string> */
    public static function employmentTypes(): array
    {
        return ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'];
    }

    protected function prepareForValidation(): void
    {
        // Allow JSON payloads to omit the multipart photo field entirely.
        if (! $this->hasFile('photo')) {
            $this->request->remove('photo');
        }
    }
}
