<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Register a new platform account. Business logic lives here, not in controllers.
 *
 * Only self-service roles are allowed at registration (job_seeker, employer_admin,
 * university_admin). Staff/admin roles are assigned by administrators only.
 */
class RegisterUser
{
    private const SELF_SERVICE_ROLES = [
        UserRole::JobSeeker->value,
        UserRole::EmployerAdmin->value,
        UserRole::UniversityAdmin->value,
    ];

    /**
     * @param  array{name: string, email: string, password: string, phone?: ?string, locale?: string, role?: string}  $data
     */
    public function handle(array $data): User
    {
        $role = $data['role'] ?? UserRole::JobSeeker->value;

        if (!in_array($role, self::SELF_SERVICE_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'This role cannot be selected during registration.',
            ]);
        }

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email already exists.',
            ]);
        }

        return DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed via model cast
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? 'en',
                'role' => $role,
                'status' => 'active',
            ]);

            // Job seekers get their candidate profile shell immediately so later
            // CV/profile steps have a stable target.
            if ($role === UserRole::JobSeeker->value) {
                $user->candidateProfile()->create([]);
            }

            // Email verification is required for all accounts (spec: registration flow).
            $user->sendEmailVerificationNotification();

            return $user->fresh();
        });
    }
}
