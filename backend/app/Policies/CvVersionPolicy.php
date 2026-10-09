<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CvVersion;
use App\Models\User;

class CvVersionPolicy
{
    public function view(User $user, CvVersion $cvVersion): bool
    {
        return $cvVersion->candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CvVersion $cvVersion): bool
    {
        return $this->view($user, $cvVersion);
    }

    public function delete(User $user, CvVersion $cvVersion): bool
    {
        return $this->view($user, $cvVersion);
    }
}
