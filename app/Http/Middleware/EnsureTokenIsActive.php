<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsActive
{
    public function __construct(
        private readonly AuthTokenService $tokens,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $currentToken = $request->user()?->currentAccessToken();

        if (! $currentToken instanceof PersonalAccessToken || ! $currentToken->is_revoked) {
            return $next($request);
        }

        // A consumed refresh token being presented again indicates possible
        // theft. Revoke the replacement pair and this session's push token too.
        if ($currentToken->can('refresh') && $currentToken->session_id !== null) {
            $this->tokens->revokeSession($currentToken->session_id);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
        ], 401);
    }
}
