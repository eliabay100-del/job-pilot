<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\Resources\JobResource;
use App\Domain\Jobs\Resources\JobSummaryResource;
use App\Domain\Search\Actions\SearchJobs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\SearchJobsRequest;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function index(SearchJobsRequest $request, SearchJobs $search): JsonResponse
    {
        Gate::authorize('viewAny', Job::class);

        $paginator = $search->handle($request->validated());

        return new JsonResponse([
            'success' => true,
            'data' => JobSummaryResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, Job $job): JsonResponse
    {
        Gate::authorize('view', $job);

        $job->load(['company.industry', 'jobCategory', 'jobRole', 'industry', 'minEducationLevel', 'skills']);

        // Atomic counter; query builder so the view bump does not touch updated_at.
        DB::table('jobs')->where('id', $job->id)->increment('views_count');

        $job->setAttribute('is_saved', $request->user()->savedJobs()->whereKey($job->id)->exists());

        return new JsonResponse(['success' => true, 'data' => (new JobResource($job))->resolve()]);
    }
}
