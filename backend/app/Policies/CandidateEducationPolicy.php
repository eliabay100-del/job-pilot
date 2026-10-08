<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateEducation;
use App\Models\User;

class CandidateEducationPolicy
{
    public function view(User $user, CandidateEducation $candidateEducation): bool
    {
        return $candidateEducation->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateEducation $candidateEducation): bool
    {
        return $this->view($user, $candidateEducation);
    }

    public function delete(User $user, CandidateEducation $candidateEducation): bool
    {
        return $this->view($user, $candidateEducation);
    }
}
