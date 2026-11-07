<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PersonalAccessToken;
use DateTimeInterface;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues and revokes the access/refresh token pair used by the admin API.
 *
 * Each login creates two Sanctum tokens that share a `session_id`:
 *
 * - access  -> short lived, sent on every API request, ability `access`
 * - refresh -> long lived, only accepted by /auth/refresh, ability `refresh`
 *
 * Refresh tokens are single-use: rotating one revokes the whole session and
 * returns a fresh pair, so a stolen token cannot be replayed.
 */
class AuthTokenService
{
    /**
     * Start a new login session.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issue(Admin $admin): array
    {
        $sessionId = (string) Str::uuid();

        $access = $this->createToken(
            admin: $admin,
            name: 'access',
            abilities: ['access'],
            expiresAt: now()->addMinutes($this->accessTtlMinutes()),
            sessionId: $sessionId,
        );

        $refresh = $this->createToken(
            admin: $admin,
            name: 'refresh',
            abilities: ['refresh'],
            expiresAt: now()->addDays($this->refreshTtlDays()),
            sessionId: $sessionId,
        );

        return [
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtlMinutes() * 60,
        ];
    }

    /**
     * Rotate a refresh token into a new pair.
     *
     * The used refresh token (and its sibling access token) are revoked before
     * the new pair is issued.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function rotate(Admin $admin, PersonalAccessToken $currentRefreshToken): array
    {
        if ($currentRefreshToken->session_id !== null) {
            $this->revokeSession($currentRefreshToken->session_id);
        } else {
            $currentRefreshToken->delete();
        }

        return $this->issue($admin);
    }

    /**
     * Revoke every token issued by one login session.
     */
    public function revokeSession(?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        PersonalAccessToken::query()
            ->where('session_id', $sessionId)
            ->delete();
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function createToken(
        Admin $admin,
        string $name,
        array $abilities,
        DateTimeInterface $expiresAt,
        string $sessionId,
    ): NewAccessToken {
        $token = $admin->createToken($name, $abilities, $expiresAt);

        // createToken() does not accept extra attributes, so the session id is
        // filled on the model it created.
        $token->accessToken->forceFill(['session_id' => $sessionId])->save();

        return $token;
    }

    private function accessTtlMinutes(): int
    {
        return (int) config('auth_tokens.access_ttl_minutes');
    }

    private function refreshTtlDays(): int
    {
        return (int) config('auth_tokens.refresh_ttl_days');
    }
}
