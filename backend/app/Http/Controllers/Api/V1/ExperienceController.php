<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Actions\RecomputeYearsExperience;
use App\Domain\Candidates\Resources\CandidateExperienceResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\ExperienceRequest;
use App\Models\CandidateExperience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ExperienceController extends Controller
{
    public function __construct(private readonly RecomputeYearsExperience $recompute)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateExperienceResource::collection(
            $profile->experiences()->orderByDesc('start_date')->orderByDesc('id')->get(),
        );
    }

    public function store(ExperienceRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $experience = $profile->experiences()->create($request->validated());
        $this->recompute->handle($profile);

        return (new CandidateExperienceResource($experience))->response()->setStatusCode(201);
    }

    public function show(Request $request, CandidateExperience $experience): CandidateExperienceResource
    {
        Gate::authorize('view', $experience);

        return new CandidateExperienceResource($experience);
    }

    public function update(ExperienceRequest $request, CandidateExperience $experience): CandidateExperienceResource
    {
        Gate::authorize('update', $experience);
        $experience->update($request->validated());
        $this->recompute->handle($experience->candidateProfile);

        return new CandidateExperienceResource($experience);
    }

    public function destroy(Request $request, CandidateExperience $experience): JsonResponse
    {
        Gate::authorize('delete', $experience);
        $profile = $experience->candidateProfile;
        $experience->delete();
        $this->recompute->handle($profile);

        return new JsonResponse(['success' => true, 'message' => 'Experience entry deleted.']);
    }
}
