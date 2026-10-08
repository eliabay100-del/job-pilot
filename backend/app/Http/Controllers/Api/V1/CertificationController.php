<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateCertificationResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\CertificationRequest;
use App\Models\CandidateCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CertificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CandidateCertificationResource::collection(
            $profile->certifications()->orderByDesc('issued_at')->orderByDesc('id')->get(),
        );
    }

    public function store(CertificationRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $certification = $profile->certifications()->create($request->validated());

        return (new CandidateCertificationResource($certification))->response()->setStatusCode(201);
    }

    public function show(Request $request, CandidateCertification $certification): CandidateCertificationResource
    {
        Gate::authorize('view', $certification);

        return new CandidateCertificationResource($certification);
    }

    public function update(CertificationRequest $request, CandidateCertification $certification): CandidateCertificationResource
    {
        Gate::authorize('update', $certification);
        $certification->update($request->validated());

        return new CandidateCertificationResource($certification);
    }

    public function destroy(Request $request, CandidateCertification $certification): JsonResponse
    {
        Gate::authorize('delete', $certification);
        $certification->delete();

        return new JsonResponse(['success' => true, 'message' => 'Certification deleted.']);
    }
}
