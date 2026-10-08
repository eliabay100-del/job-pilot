<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\CV\Actions\StoreUploadedFile;
use App\Domain\CV\Resources\DocumentResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\CV\StoreDocumentRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly StoreUploadedFile $storeFile)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DocumentResource::collection(
            $request->user()->documents()->orderByDesc('id')->paginate(20),
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->storeFile->handle($request->file('file'), $request->user());

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);
        $this->storeFile->delete($document);

        return new JsonResponse(['success' => true, 'message' => 'Document deleted.']);
    }
}
