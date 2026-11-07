<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly AuthTokenService $tokens,
    ) {
    }

    /**
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

        return $this->tokens->issue($admin);
    }

    /**
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(Admin $admin, PersonalAccessToken $refreshToken): ?array
    {
        if (
            $refreshToken->tokenable_id !== $admin->getKey()
            || $refreshToken->tokenable_type !== $admin->getMorphClass()
            || $refreshToken->is_revoked
            || ! $refreshToken->can('refresh')
            || ($refreshToken->expires_at !== null && $refreshToken->expires_at->isPast())
        ) {
            return null;
        }

        return $this->tokens->rotate($admin, $refreshToken);
    }

    public function logout(PersonalAccessToken $currentToken): void
    {
        if ($currentToken->session_id !== null) {
            $this->tokens->revokeSession($currentToken->session_id);

            return;
        }

        $currentToken->forceFill(['is_revoked' => true])->save();
    }
}
