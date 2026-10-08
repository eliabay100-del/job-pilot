<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:filter', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(['en', 'am'])],
            // Self-service roles only; admins assign other roles server-side.
            'role' => ['nullable', Rule::in([
                UserRole::JobSeeker->value,
                UserRole::EmployerAdmin->value,
                UserRole::UniversityAdmin->value,
            ])],
        ];
    }
}
