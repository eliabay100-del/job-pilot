<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\Resources\JobSummaryResource;
use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $saved = $request->user()->savedJobs()
            ->published()
            ->with(['company', 'jobCategory', 'skills'])
            ->orderByDesc('saved_jobs.created_at')
            ->get();

        return new JsonResponse(['success' => true, 'data' => JobSummaryResource::collection($saved)->resolve()]);
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        Gate::authorize('save', $job);

        $attached = $request->user()->savedJobs()->syncWithoutDetaching($job->id)['attached'];

        return new JsonResponse(
            ['success' => true, 'data' => ['is_saved' => true]],
            $attached === [] ? 200 : 201,
        );
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        $request->user()->savedJobs()->detach($job->id);

        return new JsonResponse(['success' => true, 'data' => ['is_saved' => false]]);
    }
}
