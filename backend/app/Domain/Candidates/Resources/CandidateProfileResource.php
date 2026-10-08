<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProfile */
class CandidateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'photo_url' => $this->when(
                $this->photo_path !== null,
                fn () => route('api.v1.profile.photo', $this->id),
            ),
            'years_experience' => $this->years_experience,
            'current_job_title' => $this->current_job_title,
            'current_company' => $this->current_company,
            'preferred_job_title' => $this->preferred_job_title,
            'preferred_location' => $this->preferred_location,
            'remote_preference' => $this->remote_preference,
            'employment_type_preference' => $this->employment_type_preference,
            'expected_salary_monthly_etb' => $this->expected_salary_monthly_etb,
            'availability' => $this->availability,
            'is_public' => $this->is_public,
            'preference' => new CandidatePreferenceResource($this->whenLoaded('preference')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
