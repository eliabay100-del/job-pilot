<?php

declare(strict_types=1);

namespace App\Domain\Companies\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'size' => $this->size,
            'type' => $this->type,
            'verification_status' => $this->verification_status,
            'social_links' => $this->social_links,
            'industry' => $this->whenLoaded('industry', fn () => [
                'id' => $this->industry?->id,
                'name' => $this->industry?->name,
                'slug' => $this->industry?->slug,
            ]),
            'jobs_count' => $this->whenCounted('jobs'),
        ];
    }
}
