<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateProject;
use App\Models\User;

class CandidateProjectPolicy
{
    public function view(User $user, CandidateProject $candidateProject): bool
    {
        return $candidateProject->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateProject $candidateProject): bool
    {
        return $this->view($user, $candidateProject);
    }

    public function delete(User $user, CandidateProject $candidateProject): bool
    {
        return $this->view($user, $candidateProject);
    }
}
