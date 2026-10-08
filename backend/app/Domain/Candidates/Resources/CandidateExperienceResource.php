<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateExperience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateExperience */
class CandidateExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_title' => $this->job_title,
            'company' => $this->company,
            'employment_type' => $this->employment_type,
            'location' => $this->location,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_current' => $this->is_current,
            'description' => $this->description,
            'achievements' => $this->achievements,
            'responsibilities' => $this->responsibilities,
        ];
    }
}
