<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidatePreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidatePreference */
class CandidatePreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'work_modes' => $this->work_modes,
            'locations' => $this->locations,
            'employment_types' => $this->employment_types,
            'industries' => $this->industries,
            'job_category_ids' => $this->job_category_ids,
            'desired_job_role_ids' => $this->desired_job_role_ids,
            'desired_industry_ids' => $this->desired_industry_ids,
            'salary_min_monthly_eth' => $this->salary_min_monthly_eth,
            'experience_level' => $this->experience_level,
            'open_to_remote' => $this->open_to_remote,
            'willing_to_relocate' => $this->willing_to_relocate,
        ];
    }
}
