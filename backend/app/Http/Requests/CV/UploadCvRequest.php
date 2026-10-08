<?php

declare(strict_types=1);

namespace App\Http\Requests\CV;

use Illuminate\Foundation\Http\FormRequest;

class UploadCvRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // SPEC section 38: extension + MIME + size validated server-side.
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:5120'],
            'title' => ['nullable', 'string', 'max:160'],
        ];
    }
}
