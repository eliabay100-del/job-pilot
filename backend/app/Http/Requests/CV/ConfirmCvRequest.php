<?php

declare(strict_types=1);

namespace App\Http\Requests\CV;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCvRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:name,location,summary'],
        ];
    }
}