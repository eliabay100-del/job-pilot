<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateProjectResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\ProjectRequest;
use App\Models\CandidateProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateProjectResource::collection(
            $profile->projects()->orderByDesc('id')->get(),
        );
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $project = $profile->projects()->create($request->validated() + ['source' => 'manual']);

        return (new CandidateProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(Request $request, CandidateProject $project): CandidateProjectResource
    {
        Gate::authorize('view', $project);

        return new CandidateProjectResource($project);
    }

    public function update(ProjectRequest $request, CandidateProject $project): CandidateProjectResource
    {
        Gate::authorize('update', $project);
        $project->update($request->validated());

        return new CandidateProjectResource($project);
    }

    public function destroy(Request $request, CandidateProject $project): JsonResponse
    {
        Gate::authorize('delete', $project);
        $project->delete();

        return new JsonResponse(['success' => true, 'message' => 'Project deleted.']);
    }
}
