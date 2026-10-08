<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CandidateCertification;
use App\Models\User;

class CandidateCertificationPolicy
{
    public function view(User $user, CandidateCertification $candidateCertification): bool
    {
        return $candidateCertification->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateCertification $candidateCertification): bool
    {
        return $this->view($user, $candidateCertification);
    }

    public function delete(User $user, CandidateCertification $candidateCertification): bool
    {
        return $this->view($user, $candidateCertification);
    }
}
