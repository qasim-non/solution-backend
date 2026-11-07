<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PersonalAccessToken;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues and revokes the access/refresh token pair used by the admin API.
 *
 * Each login creates two Sanctum tokens that share a session_id:
 * - access: short-lived and accepted by protected API routes
 * - refresh: long-lived and accepted only by the refresh endpoint
 */
class AuthTokenService
{
    /**
     * Start a new independent login session.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issue(Admin $admin): array
    {
        return DB::transaction(function () use ($admin): array {
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
        });
    }

    /**
     * Atomically consume a refresh token and issue a replacement pair.
     *
     * The row lock prevents two concurrent requests from successfully rotating
     * the same refresh token on database engines that support FOR UPDATE.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function rotate(Admin $admin, PersonalAccessToken $currentRefreshToken): ?array
    {
        return DB::transaction(function () use ($admin, $currentRefreshToken): ?array {
            $lockedToken = PersonalAccessToken::query()
                ->whereKey($currentRefreshToken->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedToken
                || $lockedToken->tokenable_id !== $admin->getKey()
                || $lockedToken->tokenable_type !== $admin->getMorphClass()
                || ! $lockedToken->can('refresh')
                || ($lockedToken->expires_at !== null && $lockedToken->expires_at->isPast())
            ) {
                return null;
            }

            if ($lockedToken->session_id !== null) {
                $this->revokeSession($lockedToken->session_id);
            } else {
                $lockedToken->delete();
            }

            return $this->issue($admin);
        });
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
