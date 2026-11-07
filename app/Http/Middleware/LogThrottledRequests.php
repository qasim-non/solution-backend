<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record rate-limit rejections (HTTP 429) in a dedicated security log.
 *
 * Laravel's throttle middleware only returns 429 and does not log anything.
 * This global middleware runs outside the router, so it sees the final status
 * code and can record the attempt without changing the response.
 */
class LogThrottledRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 429) {
            Log::channel('security')->warning('Rate limit exceeded.', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'retry_after' => $response->headers->get('Retry-After'),
            ]);
        }

        return $response;
    }
}
