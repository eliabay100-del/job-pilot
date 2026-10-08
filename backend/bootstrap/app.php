<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standard JSON error envelope for the versioned API.
        // See docs/SPEC.md "Error handling" and app/Exceptions/ApiExceptionHandler.php.
        // NOTE (verified against laravel/framework v12.69 Handler::renderViaCallbacks):
        // render callbacks receive ($exception, $request) — exception FIRST — and
        // dispatch on the FIRST parameter's type hint only. A Throwable first-hint
        // never matches concrete exceptions, so we register one closure per
        // exception family with the concrete class as the first type hint.
        foreach ([
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class,
        ] as $type) {
            $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) use ($type) {
                if (!$exception instanceof $type) {
                    return null;
                }
                return \App\Exceptions\ApiExceptionHandler::render($request, $exception);
            });
        }
    })->create();
