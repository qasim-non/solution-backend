<?php

namespace App\Http\Controllers;

use App\Http\Requests\loginRequest;
use App\Models\Admin;
use App\Models\PersonalAccessToken;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(loginRequest $login, AuthTokenService $tokens)
    {
        $loginInfoValidated = $login->validated();

        $admin = Admin::getAdmin($loginInfoValidated);

        if (! $admin || ! Hash::check($loginInfoValidated['password'], $admin['password'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password.',
            ], 401);
        }

        // Keep existing sessions valid so logging in on one device/browser
        // does not silently log out the others.
        $pair = $tokens->issue($admin);

        return response()->json([
            'message' => 'Admin login successful.',
            ...$pair,
        ], 200);
    }

    /**
     * Exchange a valid refresh token for a new access/refresh pair.
     *
     * Refresh tokens are single-use: the used pair is revoked before the new
     * one is returned.
     */
    public function refresh(Request $request, AuthTokenService $tokens): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Invalid refresh token.'], 401);
        }

        return response()->json([
            'message' => 'Token refreshed successfully.',
            ...$tokens->rotate($request->user(), $current),
        ], 200);
    }

    /**
     * Revoke the current login session (access and refresh token).
     */
    public function logout(Request $request, AuthTokenService $tokens): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        if ($current instanceof PersonalAccessToken) {
            if ($current->session_id !== null) {
                $tokens->revokeSession($current->session_id);
            } else {
                $current->delete();
            }
        }

        return response()->json(['message' => 'Admin logged out successfully.'], 200);
    }
}
