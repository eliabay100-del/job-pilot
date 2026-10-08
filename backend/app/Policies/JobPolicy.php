<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Job $job): bool
    {
        return $job->isPublished() || $this->manages($user, $job);
    }

    public function save(User $user, Job $job): bool
    {
        return $job->isPublished();
    }

    private function manages(User $user, Job $job): bool
    {
        if ($job->created_by === $user->id) {
            return true;
        }

        return $job->company_id !== null
            && $user->companyMemberships()
                ->where('company_id', $job->company_id)
                ->where('status', 'active')
                ->exists();
    }
}
