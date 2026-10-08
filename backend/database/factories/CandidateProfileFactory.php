<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CandidateProfile> */
class CandidateProfileFactory extends Factory
{
    protected $model = CandidateProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory()->state([
                'role' => 'job_seeker',
                'email_verified_at' => now(),
            ]),
        ];
    }
}
