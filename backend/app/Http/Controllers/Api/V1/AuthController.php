<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\LoginUser;
use App\Domain\Auth\Actions\RegisterUser;
use App\Domain\Auth\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller: validation via Form Requests, logic via Domain Actions,
 * output via API Resources. No business logic here.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUser->handle($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Account created. Check your email to verify your account.'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->loginUser->handle($request->validated()['email'], $request->validated()['password']);

        $token = $user->createToken(
            $request->validated('device_name') ?? 'api',
        )->plainTextToken;

        return new JsonResponse([
            'success' => true,
            'data' => ['user' => new UserResource($user), 'token' => $token],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        // Delete by token id directly: within the same request lifecycle, Sanctum's
        // in-memory currentAccessToken can make relationship-level deletes match 0 rows.
        $token = $user->currentAccessToken();
        if ($token !== null) {
            \Laravel\Sanctum\PersonalAccessToken::whereKey($token->id)->delete();
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
