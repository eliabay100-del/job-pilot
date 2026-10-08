<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateLanguageResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\LanguageRequest;
use App\Models\CandidateLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LanguageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateLanguageResource::collection(
            $profile->languages()->orderBy('id')->get(),
        );
    }

    public function store(LanguageRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $language = $profile->languages()->create($request->validated());

        return (new CandidateLanguageResource($language))->response()->setStatusCode(201);
    }

    public function update(LanguageRequest $request, CandidateLanguage $language): CandidateLanguageResource
    {
        Gate::authorize('update', $language);
        $language->update($request->validated());

        return new CandidateLanguageResource($language);
    }

    public function destroy(Request $request, CandidateLanguage $language): JsonResponse
    {
        Gate::authorize('delete', $language);
        $language->delete();

        return new JsonResponse(['success' => true, 'message' => 'Language removed.']);
    }
}
