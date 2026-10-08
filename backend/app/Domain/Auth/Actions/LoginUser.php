<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authenticate with email + password. Returns the user on success.
 * Throws a generic credential error (no account enumeration).
 */
class LoginUser
{
    public function handle(string $email, string $password): User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if ($user === null || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Account state is enforced server-side, never trusted from the client.
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact support.',
            ]);
        }

        return $user;
    }
}
