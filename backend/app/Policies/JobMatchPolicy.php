<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobMatch;
use App\Models\User;

class JobMatchPolicy
{
    /** Matches are always computed for the requesting user's own profile. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JobMatch $match): bool
    {
        return (int) $match->candidate_profile_id === (int) ($user->candidateProfile?->id ?? 0);
    }
}
