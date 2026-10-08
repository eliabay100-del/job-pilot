<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateEducation */
class CandidateEducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'institution_name' => $this->institution_name ?? $this->whenLoaded('institution', fn () => $this->institution?->name),
            'degree' => $this->degree,
            'education_level_id' => $this->education_level_id,
            'education_level' => $this->whenLoaded('educationLevel', fn () => $this->educationLevel?->name),
            'field_of_study' => $this->field_of_study,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_year' => $this->start_year,
            'end_year' => $this->end_year,
            'currently_studying' => $this->currently_studying,
            'cgpa' => $this->cgpa,
            'grade' => $this->grade,
            'notes' => $this->notes,
            'description' => $this->description,
            'achievements' => $this->achievements,
        ];
    }
}
