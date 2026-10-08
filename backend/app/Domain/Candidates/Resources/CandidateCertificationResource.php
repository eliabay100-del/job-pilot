<?php

declare(strict_types=1);

namespace App\Domain\Candidates\Resources;

use App\Models\CandidateCertification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateCertification */
class CandidateCertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'issuer' => $this->issuer,
            'credential_id' => $this->credential_id,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'url' => $this->url,
        ];
    }
}
