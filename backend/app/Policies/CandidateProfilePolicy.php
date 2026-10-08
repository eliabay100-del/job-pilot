<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    public function view(User $user, CandidateProfile $candidateProfile): bool
    {
        return $user->id === $candidateProfile->user_id;
    }

    public function update(User $user, CandidateProfile $candidateProfile): bool
    {
        return $user->id === $candidateProfile->user_id;
    }
}
