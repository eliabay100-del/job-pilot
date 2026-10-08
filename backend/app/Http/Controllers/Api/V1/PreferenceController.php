<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidatePreferenceResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\UpdatePreferencesRequest;
use App\Models\CandidatePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        $preference = $profile->preference ?? $profile->preference()->create([]);

        // Pin 200: both methods may create the row lazily, and a resource would
        // otherwise report 201 for a freshly created model.
        return $this->respond($preference);
    }

    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $preference = $profile->preference()->updateOrCreate(
            ['candidate_profile_id' => $profile->id],
            $request->validated(),
        );

        return $this->respond($preference);
    }

    private function respond(CandidatePreference $preference): JsonResponse
    {
        return (new CandidatePreferenceResource($preference))->response()->setStatusCode(200);
    }
}
