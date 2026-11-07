<?php

namespace App\Http\Controllers;

use App\Http\Requests\loginRequest;
use App\Models\Admin;
use App\Models\PersonalAccessToken;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Authenticate an admin and issue a new access/refresh token pair.
     */
    public function login(loginRequest $request, AuthService $auth): JsonResponse
    {
        $tokens = $auth->login($request->validated());

        if ($tokens === null) {
            return $this->unauthorized('Invalid username or password.');
        }

        return $this->success($tokens, 'Admin login successful.');
    }

    /**
     * Exchange a valid refresh token for a new access/refresh pair.
     */
    public function refresh(Request $request, AuthService $auth): JsonResponse
    {
        $admin = $request->user();
        $currentToken = $admin?->currentAccessToken();

        if (! $admin instanceof Admin || ! $currentToken instanceof PersonalAccessToken) {
            return $this->unauthorized('Invalid refresh token.');
        }

        $tokens = $auth->refresh($admin, $currentToken);

        if ($tokens === null) {
            return $this->unauthorized('Invalid refresh token.');
        }

        return $this->success($tokens, 'Token refreshed successfully.');
    }

    /**
     * Revoke the current login session (access and refresh token).
     */
    public function logout(Request $request, AuthService $auth): JsonResponse
    {
        $currentToken = $request->user()?->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $auth->logout($currentToken);
        }

        return $this->success(null, 'Admin logged out successfully.');
    }
}
