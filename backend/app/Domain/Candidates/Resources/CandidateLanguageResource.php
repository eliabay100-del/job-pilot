<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateLanguage */
class CandidateLanguageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'language' => $this->language,
            'proficiency' => $this->proficiency,
            'speaking_level' => $this->speaking_level,
            'listening_level' => $this->listening_level,
            'reading_level' => $this->reading_level,
            'writing_level' => $this->writing_level,
        ];
    }
}
