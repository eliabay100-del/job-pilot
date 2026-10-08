<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** GET /api/v1/auth/email/verify/{id}/{hash} — handled via signed URL. */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return new JsonResponse([
            'success' => true,
            'message' => 'Email verified.',
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return new JsonResponse(['success' => true, 'message' => 'Already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return new JsonResponse(['success' => true, 'message' => 'Verification link sent.']);
    }
}
