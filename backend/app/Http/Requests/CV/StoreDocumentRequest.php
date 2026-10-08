<?php

declare(strict_types=1);

namespace App\Http\Requests\CV;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,png,jpg,jpeg',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/png,image/jpeg',
                'max:10240',
            ],
        ];
    }
}
