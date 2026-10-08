<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Standard JSON error envelope for the versioned API (docs/SPEC.md "Error handling"):
 *
 *   { "success": false, "message": "...", "code": "MACHINE_CODE", "request_id": "..." }
 *
 * Never expose stack traces, SQL errors, secrets or internal paths in production.
 */
class ApiExceptionHandler
{
    public static function render(Request $request, Throwable $e): ?JsonResponse
    {
        // Only handle API calls; let web defaults stand otherwise.
        if (!self::isApiRequest($request)) {
            return null;
        }

        [$status, $message, $code, $errors] = match (true) {
            $e instanceof ValidationException => [
                422,
                'The given data was invalid.',
                'VALIDATION_FAILED',
                $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401,
                'Authentication required.',
                'UNAUTHENTICATED',
                null,
            ],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                404,
                'Resource not found.',
                'NOT_FOUND',
                null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                'HTTP_ERROR',
                null,
            ],
            default => [
                500,
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                'INTERNAL_ERROR',
                null,
            ],
        };

        // In production we must never leak internal exception messages.
        if (!config('app.debug') && $status === 500) {
            $message = 'An unexpected error occurred.';
        }

        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
            'request_id' => $request->header('X-Request-Id') ?: (string) \Illuminate\Support\Str::uuid(),
        ], $status);
    }

    private static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
