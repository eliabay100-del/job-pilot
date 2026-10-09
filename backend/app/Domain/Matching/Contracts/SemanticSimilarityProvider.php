<?php

declare(strict_types=1);

namespace App\Domain\Matching\Contracts;

use App\Models\CandidateProfile;
use App\Models\Job;

/**
 * Semantic fit between a candidate and a job, 0..100. Returning null excludes
 * the component from the weighted score, which is how the deterministic engine
 * behaves until embeddings exist (pgvector is not installed locally).
 */
interface SemanticSimilarityProvider
{
    public function similarity(CandidateProfile $profile, Job $job): ?float;

    public function name(): string;
}
