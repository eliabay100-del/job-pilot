<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProject */
class CandidateProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'role' => $this->role,
            'url' => $this->url,
            'repository_url' => $this->repository_url,
            'source' => $this->source,
            'tech_stack' => $this->tech_stack,
            'started_at' => $this->started_at?->toDateString(),
            'completed_at' => $this->completed_at?->toDateString(),
            'achievements' => $this->achievements,
        ];
    }
}
