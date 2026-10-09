<?php

declare(strict_types=1);

namespace App\Http\Requests\CV;

use Illuminate\Foundation\Http\FormRequest;

class TailorCvRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_job_id' => ['required', 'integer', 'exists:jobs,id'],
        ];
    }
}