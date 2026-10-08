<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Resources;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Job */
class JobSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'work_mode' => $this->work_mode,
            'employment_type' => $this->employment_type,
            'seniority' => $this->seniority,
            'salary' => [
                'min_monthly' => $this->salary_min_monthly,
                'max_monthly' => $this->salary_max_monthly,
                'currency' => $this->salary_currency,
                'negotiable' => $this->salary_negotiable,
            ],
            'experience_years_min' => $this->experience_years_min,
            'experience_years_max' => $this->experience_years_max,
            'application_deadline' => $this->application_deadline?->toDateString(),
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'slug' => $this->company?->slug,
                'verification_status' => $this->company?->verification_status,
            ]),
            'job_category' => $this->whenLoaded('jobCategory', fn () => [
                'id' => $this->jobCategory?->id,
                'name' => $this->jobCategory?->name,
            ]),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills
                ->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'is_required' => (bool) $skill->pivot->is_required,
                ])
                ->all()),
        ];
    }
}
