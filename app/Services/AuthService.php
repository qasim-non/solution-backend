<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

/**
 * Coordinates the admin authentication use cases.
 *
 * Token creation and revocation details remain in AuthTokenService.
 */
class AuthService
{
    public function __construct(
        private readonly AuthTokenService $tokens,
    ) {
    }

    /**
     * Authenticate an admin and start a new independent login session.
     *
     * @param  array{username: string, password: string}  $credentials
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function login(array $credentials): ?array
    {
        $admin = Admin::query()
            ->where('username', $credentials['username'])
            ->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            return null;
        }

        // A new login does not invalidate sessions on other devices.
        return $this->tokens->issue($admin);
    }

    /**
     * Rotate a refresh token for the authenticated admin.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(Admin $admin, PersonalAccessToken $refreshToken): ?array
    {
        if (
            $refreshToken->tokenable_id !== $admin->getKey()
            || $refreshToken->tokenable_type !== $admin->getMorphClass()
            || ! $refreshToken->can('refresh')
            || ($refreshToken->expires_at !== null && $refreshToken->expires_at->isPast())
        ) {
            return null;
        }

        return $this->tokens->rotate($admin, $refreshToken);
    }

    /**
     * Revoke the login session associated with the current token.
     */
    public function logout(PersonalAccessToken $currentToken): void
    {
        if ($currentToken->session_id !== null) {
            $this->tokens->revokeSession($currentToken->session_id);

            return;
        }

        $currentToken->delete();
    }
}
