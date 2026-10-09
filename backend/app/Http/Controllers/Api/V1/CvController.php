<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Actions\ParseCv;
use App\Domain\AI\Actions\ConfirmCvExtraction;
use App\Domain\AI\Actions\TailorCv;
use App\Domain\CV\Actions\StoreUploadedFile;
use App\Domain\CV\Resources\CvVersionResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\CV\UploadCvRequest;
use App\Http\Requests\CV\ConfirmCvRequest;
use App\Http\Requests\CV\TailorCvRequest;
use App\Models\Job;
use App\Models\CvVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvController extends Controller
{
    public function __construct(
        private readonly StoreUploadedFile $storeFile,
        private readonly ParseCv $parseCv,
        private readonly ConfirmCvExtraction $confirmCvExtraction,
        private readonly TailorCv $tailorCv,
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('view', $profile);

        return CvVersionResource::collection(
            $profile->cvVersions()->with('document')->orderByDesc('version_number')->paginate(20),
        );
    }

    public function upload(UploadCvRequest $request): JsonResponse
    {
        $profile = $request->user()->ensureCandidateProfile();
        Gate::authorize('update', $profile);

        $document = $this->storeFile->handle($request->file('file'), $request->user());

        $version = $profile->cvVersions()->create([
            'document_id' => $document->id,
            'title' => $request->validated('title') ?? 'Main CV',
            'kind' => 'uploaded',
            'parse_status' => 'pending', // CV parsing arrives in Phase 5
            'version_number' => (int) $profile->cvVersions()->max('version_number') + 1,
        ]);

        return (new CvVersionResource($version->load('document')))->response()->setStatusCode(201);
    }

    public function show(Request $request, CvVersion $cv): CvVersionResource
    {
        Gate::authorize('view', $cv);

        return new CvVersionResource($cv->load('document'));
    }

    public function parse(Request $request, CvVersion $cv): CvVersionResource
    {
        Gate::authorize('update', $cv);

        return new CvVersionResource($this->parseCv->handle($cv));
    }

    public function confirm(ConfirmCvRequest $request, CvVersion $cv): CvVersionResource
    {
        Gate::authorize('update', $cv);

        return new CvVersionResource($this->confirmCvExtraction->handle($cv, $request->validated('fields')));
    }

    public function tailor(TailorCvRequest $request, CvVersion $cv): JsonResponse
    {
        Gate::authorize('update', $cv);
        $job = Job::with('skills')->findOrFail($request->validated('target_job_id'));
        Gate::authorize('view', $job);

        return (new CvVersionResource($this->tailorCv->handle($cv, $job)))
            ->response()->setStatusCode(200);
    }

    public function download(Request $request, CvVersion $cv): StreamedResponse
    {
        Gate::authorize('view', $cv);

        $document = $cv->document;
        abort_if($document === null, 404, 'This CV version has no attached file.');

        return $this->streamDocument($document->disk, $document->path, $document->original_name);
    }

    public function destroy(Request $request, CvVersion $cv): JsonResponse
    {
        Gate::authorize('delete', $cv);

        $document = $cv->document;
        $cv->delete();

        // Delete the backing file only when no other CV version references it.
        if ($document !== null && $document->cvVersions()->doesntExist()) {
            $this->storeFile->delete($document);
        }

        return new JsonResponse(['success' => true, 'message' => 'CV version deleted.']);
    }

    private function streamDocument(string $disk, string $path, string $name): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name);
    }
}
