<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Matching\Actions\ComputeJobMatch;
use App\Domain\Matching\MatchResult;
use App\Domain\Matching\Resources\JobMatchResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Matching\IndexMatchesRequest;
use App\Models\Job;
use App\Models\JobMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchController extends Controller
{
    public function index(IndexMatchesRequest $request, ComputeJobMatch $compute): JsonResponse
    {
        Gate::authorize('viewAny', JobMatch::class);

        $profile = $request->user()->ensureCandidateProfile();
        $jobs = $compute->matchableJobs((int) config('matching.max_jobs_per_request'));
        $results = $compute->handleMany($profile, $jobs);
        $byJobId = $jobs->keyBy('id');

        usort($results, fn (MatchResult $a, MatchResult $b) => [$b->overallScore, $a->jobId] <=> [$a->overallScore, $b->jobId]);

        $minScore = $request->validated('min_score');
        if ($minScore !== null && $minScore !== '') {
            $results = array_values(array_filter(
                $results,
                fn (MatchResult $result) => $result->overallScore >= (float) $minScore,
            ));
        }

        $limit = (int) $request->validated('limit');
        $total = count($results);
        $results = array_slice($results, 0, $limit);

        $data = [];
        foreach ($results as $result) {
            $job = $byJobId->get($result->jobId);
            if ($job === null) {
                continue;
            }

            $data[] = (new JobMatchResource($result, $job))->resolve();
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'returned' => count($data),
                'scored_jobs' => $jobs->count(),
                'model_version' => (string) config('matching.model_version'),
            ],
        ]);
    }

    public function show(Request $request, Job $job, ComputeJobMatch $compute): JsonResponse
    {
        Gate::authorize('view', $job);
        Gate::authorize('viewAny', JobMatch::class);

        $job->loadMissing(['company', 'jobCategory', 'skills']);

        $result = $compute->handle($request->user()->ensureCandidateProfile(), $job);

        return new JsonResponse([
            'success' => true,
            'data' => (new JobMatchResource($result, $job))->resolve(),
        ]);
    }
}
