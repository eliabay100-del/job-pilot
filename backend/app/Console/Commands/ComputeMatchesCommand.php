<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Matching\Actions\ComputeJobMatch;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[AsCommand(
    name: 'matching:compute',
    description: 'Recompute deterministic job matches and store them in job_matches',
)]
class ComputeMatchesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'matching:compute
                            {--profile= : Candidate profile id (default: every profile)}
                            {--job= : Job id (default: every published job)}
                            {--limit=200 : Maximum jobs to score per profile}';

    protected $description = 'Recompute deterministic job matches and store them in job_matches';

    public function handle(ComputeJobMatch $compute): int
    {
        $jobs = $this->jobs($compute);

        if ($jobs->isEmpty()) {
            $this->warn('No jobs to score.');

            return self::SUCCESS;
        }

        $profileOption = $this->option('profile');
        $scored = 0;
        $profiles = 0;

        CandidateProfile::query()
            ->when($profileOption !== null, fn ($query) => $query->whereKey((int) $profileOption))
            ->with(['skills', 'educations.educationLevel', 'preference'])
            ->chunkById(100, function ($chunk) use ($compute, $jobs, &$scored, &$profiles): void {
                foreach ($chunk as $profile) {
                    $compute->handleMany($profile, $jobs);
                    $scored += $jobs->count();
                    $profiles++;
                }
            });

        $this->table(
            ['profiles', 'jobs', 'matches stored', 'model_version'],
            [[$profiles, $jobs->count(), $scored, (string) config('matching.model_version')]],
        );

        return self::SUCCESS;
    }

    /** @return Collection<int, Job> */
    private function jobs(ComputeJobMatch $compute): Collection
    {
        $jobOption = $this->option('job');

        if ($jobOption === null) {
            return $compute->matchableJobs((int) $this->option('limit'));
        }

        return Job::query()
            ->with(['skills', 'minEducationLevel', 'company', 'jobCategory', 'jobRole', 'industry'])
            ->whereKey((int) $jobOption)
            ->get();
    }
}
