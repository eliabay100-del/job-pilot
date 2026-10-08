<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateSkill */
class CandidateSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'skill' => $this->whenLoaded('skill', fn () => $this->skill?->name),
            'level' => $this->level,
            'years_using' => $this->years_using,
            'source' => $this->source,
        ];
    }
}
