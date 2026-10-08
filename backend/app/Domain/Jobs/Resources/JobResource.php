<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Resources;

use Illuminate\Http\Request;

class JobResource extends JobSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'responsibilities' => $this->responsibilities,
            'requirements' => $this->requirements,
            'qualifications' => $this->qualifications,
            'benefits' => $this->benefits,
            'min_education_level' => $this->whenLoaded('minEducationLevel', fn () => [
                'id' => $this->minEducationLevel?->id,
                'name' => $this->minEducationLevel?->name,
            ]),
            'job_role' => $this->whenLoaded('jobRole', fn () => [
                'id' => $this->jobRole?->id,
                'name' => $this->jobRole?->name,
            ]),
            'industry' => $this->whenLoaded('industry', fn () => [
                'id' => $this->industry?->id,
                'name' => $this->industry?->name,
            ]),
            'source_url' => $this->source_url,
            'views_count' => $this->views_count,
            'is_saved' => $this->when(
                $this->resource->getAttribute('is_saved') !== null,
                fn () => (bool) $this->resource->getAttribute('is_saved'),
            ),
        ];
    }
}
