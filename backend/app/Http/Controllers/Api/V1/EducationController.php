<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateEducationResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\EducationRequest;
use App\Models\CandidateEducation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EducationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateEducationResource::collection(
            $profile->educations()->with(['institution', 'educationLevel'])
                ->orderByDesc('end_year')->orderByDesc('id')->get(),
        );
    }

    public function store(EducationRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $education = $profile->educations()->create($request->validated());

        return (new CandidateEducationResource($education->load(['institution', 'educationLevel'])))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, CandidateEducation $education): CandidateEducationResource
    {
        Gate::authorize('view', $education);

        return new CandidateEducationResource($education->load(['institution', 'educationLevel']));
    }

    public function update(EducationRequest $request, CandidateEducation $education): CandidateEducationResource
    {
        Gate::authorize('update', $education);
        $education->update($request->validated());

        return new CandidateEducationResource($education->load(['institution', 'educationLevel']));
    }

    public function destroy(Request $request, CandidateEducation $education): JsonResponse
    {
        Gate::authorize('delete', $education);
        $education->delete();

        return new JsonResponse(['success' => true, 'message' => 'Education entry deleted.']);
    }
}
