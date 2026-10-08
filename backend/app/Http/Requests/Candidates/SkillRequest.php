<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use App\Models\CandidateSkill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkillRequest extends FormRequest
{
    public function rules(): array
    {
        $store = $this->isMethod('POST');
        $profileId = $this->user()->candidateProfile?->id;

        $skillRule = Rule::unique(CandidateSkill::class, 'skill_id')
            ->where('candidate_profile_id', $profileId);

        if (! $store && $this->route('skill') instanceof CandidateSkill) {
            $skillRule = $skillRule->ignore($this->route('skill')->id);
        }

        return [
            'skill_id' => [$store ? 'required' : 'sometimes', 'integer', 'exists:skills,id', $skillRule],
            'level' => [$store ? 'required' : 'sometimes', 'integer', 'min:1', 'max:5'],
            'years_using' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }
}
