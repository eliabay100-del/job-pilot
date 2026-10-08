<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use App\Models\CandidateLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    private const LEVELS = ['basic', 'working', 'professional', 'native'];

    public function rules(): array
    {
        $store = $this->isMethod('POST');
        $profileId = $this->user()->candidateProfile?->id;

        $languageRule = Rule::unique(CandidateLanguage::class, 'language')
            ->where('candidate_profile_id', $profileId);

        if (! $store && $this->route('language') instanceof CandidateLanguage) {
            $languageRule = $languageRule->ignore($this->route('language')->id);
        }

        return [
            'language' => [$store ? 'required' : 'sometimes', 'string', 'max:80', $languageRule],
            'proficiency' => [$store ? 'required' : 'sometimes', Rule::in(self::LEVELS)],
            'speaking_level' => ['nullable', Rule::in(self::LEVELS)],
            'listening_level' => ['nullable', Rule::in(self::LEVELS)],
            'reading_level' => ['nullable', Rule::in(self::LEVELS)],
            'writing_level' => ['nullable', Rule::in(self::LEVELS)],
        ];
    }
}
