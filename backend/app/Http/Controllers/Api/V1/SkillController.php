<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateSkillResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\SkillRequest;
use App\Models\CandidateSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SkillController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateSkillResource::collection(
            $profile->skills()->with('skill')->orderByDesc('level')->orderBy('id')->get(),
        );
    }

    public function store(SkillRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $skill = $profile->skills()->create(
            $request->validated() + ['source' => 'manual', 'confidence' => 1.00],
        );

        return (new CandidateSkillResource($skill->load('skill')))->response()->setStatusCode(201);
    }

    public function update(SkillRequest $request, CandidateSkill $skill): CandidateSkillResource
    {
        Gate::authorize('update', $skill);
        $skill->update($request->validated());

        return new CandidateSkillResource($skill->load('skill'));
    }

    public function destroy(Request $request, CandidateSkill $skill): JsonResponse
    {
        Gate::authorize('delete', $skill);
        $skill->delete();

        return new JsonResponse(['success' => true, 'message' => 'Skill removed.']);
    }
}
