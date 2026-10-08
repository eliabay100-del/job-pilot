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
        // The framework default is redirectGuestsTo(fn () => route('login')). This
        // is an API-only backend with no "login" named route, so that callback
        // throws RouteNotFoundException from inside the auth middleware for any
        // client that omits "Accept: application/json" — turning a 401 into a 500.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is an API-only backend: there is no web "login" route, so the
        // framework's guest-redirect fallback for unauthenticated requests would
        // throw RouteNotFoundException and turn a 401 into a 500 for any client
        // that omits "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request, \Throwable $e) => \App\Exceptions\ApiExceptionHandler::isApiRequest($request),
        );

        // Standard JSON error envelope for the versioned API.
        // See docs/SPEC.md "Error handling" and app/Exceptions/ApiExceptionHandler.php.
        // Handler::renderViaCallbacks dispatches on the first closure parameter
        // type, so a Throwable hint matches every exception and ApiExceptionHandler
        // does the family dispatch internally. It returns null for non-API
        // requests, leaving Laravel's default rendering in place.
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            return \App\Exceptions\ApiExceptionHandler::render($request, $exception);
        });
    })->create();
