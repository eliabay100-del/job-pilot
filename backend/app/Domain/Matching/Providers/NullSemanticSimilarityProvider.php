<?php

declare(strict_types=1);

namespace App\Domain\Matching\Providers;

use App\Domain\Matching\Contracts\SemanticSimilarityProvider;
use App\Models\CandidateProfile;
use App\Models\Job;

/**
 * Default binding. Embedding-based similarity needs pgvector, which is not
 * installed in every environment, so the semantic component is reported as
 * unavailable and its weight drops out of the score.
 */
class NullSemanticSimilarityProvider implements SemanticSimilarityProvider
{
    public function similarity(CandidateProfile $profile, Job $job): ?float
    {
        return null;
    }

    public function name(): string
    {
        return 'none';
    }
}
