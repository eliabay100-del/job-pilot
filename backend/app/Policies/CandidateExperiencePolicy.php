<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateExperience;
use App\Models\User;

class CandidateExperiencePolicy
{
    public function view(User $user, CandidateExperience $candidateExperience): bool
    {
        return $candidateExperience->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateExperience $candidateExperience): bool
    {
        return $this->view($user, $candidateExperience);
    }

    public function delete(User $user, CandidateExperience $candidateExperience): bool
    {
        return $this->view($user, $candidateExperience);
    }
}
