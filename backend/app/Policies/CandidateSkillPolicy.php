<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateSkill;
use App\Models\User;

class CandidateSkillPolicy
{
    public function view(User $user, CandidateSkill $candidateSkill): bool
    {
        return $candidateSkill->candidateProfile->user_id === $user->id;
    }

    public function delete(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->view($user, $candidateSkill);
    }
}
