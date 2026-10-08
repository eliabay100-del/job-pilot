<?php

declare(strict_types=1);

namespace App\Domain\CV\Resources;

use App\Models\CvVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvVersion */
class CvVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind,
            'version_number' => $this->version_number,
            'parse_status' => $this->parse_status,
            'parse_confidence' => $this->parse_confidence,
            'structured_data' => $this->when(
                in_array($this->parse_status, ['needs_confirmation', 'confirmed'], true),
                fn () => $this->structured_data,
            ),
            'document' => $this->whenLoaded('document', fn () => [
                'id' => $this->document?->id,
                'original_name' => $this->document?->original_name,
                'mime_type' => $this->document?->mime_type,
                'size_bytes' => $this->document?->size_bytes,
                'scan_status' => $this->document?->scan_status,
            ]),
            'download_url' => $this->when(
                $this->document_id !== null,
                fn () => route('api.v1.cv.download', $this->id),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
