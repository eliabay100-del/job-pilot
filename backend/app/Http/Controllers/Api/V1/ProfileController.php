<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Candidates\Resources\CandidateProfileResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidates\UpdateProfileRequest;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        // Pin 200: the profile may be created lazily here, and a resource would
        // otherwise report 201 for a freshly created model — on a GET.
        return (new CandidateProfileResource($profile->load('preference')))
            ->response()->setStatusCode(200);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            unset($data['photo']);
            $data['photo_path'] = $request->file('photo')->store(
                "photos/{$profile->user_id}",
                ['disk' => 'local'],
            );
        }

        $profile->update($data);

        return (new CandidateProfileResource($profile->load('preference')))
            ->response()->setStatusCode(200);
    }

    public function photo(Request $request, CandidateProfile $profile): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $profile);

        abort_if($profile->photo_path === null || ! Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path);
    }
}
