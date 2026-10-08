<?php

declare(strict_types=1);

namespace App\Domain\CV\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes disk/path/sha256 internals; downloads go through the
 * access-controlled download endpoint only.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'scan_status' => $this->scan_status,
            'download_url' => route('api.v1.documents.download', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
