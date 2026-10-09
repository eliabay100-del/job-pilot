<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Domain\AI\Contracts\TextGenerationProvider;
use App\Models\AiUsage;
use App\Models\CoverLetter;
use App\Models\CvVersion;
use App\Models\Job;
use Illuminate\Validation\ValidationException;

final class GenerateCoverLetter
{
    public function __construct(private readonly TextGenerationProvider $provider)
    {
    }

    public function handle(CvVersion $source, Job $job): CoverLetter
    {
        if ($source->parse_status !== 'confirmed') {
            throw ValidationException::withMessages([
                'cv' => 'Confirm the source CV before generating a cover letter.',
            ]);
        }

        $profile = $source->candidateProfile()->with('skills.skill')->firstOrFail();
        $job->loadMissing('company');
        $startedAt = hrtime(true);
        $result = $this->provider->generate('cover_letter', [
            'candidate_name' => $profile->display_name,
            'job_title' => $job->title,
            'company_name' => $job->company?->name,
            'skills' => $profile->skills->map(fn ($skill): string => (string) $skill->skill->name)->all(),
        ]);

        $coverLetter = CoverLetter::create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'source_cv_version_id' => $source->id,
            'content' => $result->content,
            'status' => 'draft',
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'context_refs' => ['source_cv_version_id' => $source->id, 'job_id' => $job->id],
        ]);

        AiUsage::create([
            'user_id' => $profile->user_id,
            'operation' => 'cover_letter',
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'context_refs' => ['source_cv_version_id' => $source->id, 'job_id' => $job->id, 'cover_letter_id' => $coverLetter->id],
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        return $coverLetter;
    }
}