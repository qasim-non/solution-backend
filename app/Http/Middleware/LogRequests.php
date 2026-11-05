<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Log every HTTP request (method, URI, status, duration and payload)
     * when LOG_HTTP_REQUESTS=true. Disabled by default, so production
     * stays completely untouched.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        if (filter_var(env('LOG_HTTP_REQUESTS', false), FILTER_VALIDATE_BOOL)) {
            Log::info(sprintf(
                '%s %s -> %d (%dms)',
                $request->method(),
                $request->getRequestUri(),
                $response->getStatusCode(),
                (int) ((microtime(true) - $start) * 1000),
            ), [
                'ip' => $request->ip(),
                'payload' => $request->except([
                    'password',
                    'password_confirmation',
                    'token',
                    '_token',
                ]),
            ]);
        }

        return $response;
    }
}
