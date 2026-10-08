<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateLanguage;
use App\Models\User;

class CandidateLanguagePolicy
{
    public function view(User $user, CandidateLanguage $candidateLanguage): bool
    {
        return $candidateLanguage->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateLanguage $candidateLanguage): bool
    {
        return $this->view($user, $candidateLanguage);
    }

    public function delete(User $user, CandidateLanguage $candidateLanguage): bool
    {
        return $this->view($user, $candidateLanguage);
    }
}
