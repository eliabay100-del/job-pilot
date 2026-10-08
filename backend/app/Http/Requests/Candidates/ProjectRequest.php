<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        $store = $this->isMethod('POST');

        return [
            'name' => [$store ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'role' => ['nullable', 'string', 'max:160'],
            'url' => ['nullable', 'url', 'max:255'],
            'repository_url' => ['nullable', 'url', 'max:255'],
            'tech_stack' => ['nullable', 'array', 'max:50'],
            'tech_stack.*' => ['string', 'max:80'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'achievements' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
