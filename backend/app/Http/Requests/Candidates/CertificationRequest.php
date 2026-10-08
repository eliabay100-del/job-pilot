<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidates;

use Illuminate\Foundation\Http\FormRequest;

class CertificationRequest extends FormRequest
{
    public function rules(): array
    {
        $store = $this->isMethod('POST');

        return [
            'name' => [$store ? 'required' : 'sometimes', 'string', 'max:160'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'credential_id' => ['nullable', 'string', 'max:160'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
