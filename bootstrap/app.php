<?php

use App\Http\Middleware\LogRequests;
use App\Http\Middleware\LogThrottledRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LogRequests::class);

        // Runs outside the route-level throttle middleware so it can record
        // 429 rejections; it re-throws, so the response is unchanged.
        $middleware->append(LogThrottledRequests::class);

        // Laravel's default guest redirect calls route('login'), which this
        // API-only backend does not define. API clients must receive a 401
        // JSON response; only browser (web) requests go to the SPA login page.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients always get a JSON error (401/403/422/...) instead of a
        // redirect to a non-existent "login" route when they omit Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
