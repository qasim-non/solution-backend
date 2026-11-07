<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

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
            return $this->issueForSession(
                $admin,
                (string) Str::uuid(),
                now()->addDays($this->refreshTtlDays()),
            );
        });
    }

    /**
     * Atomically rotate a refresh token without extending the session deadline.
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

            if (! $lockedToken
                || $lockedToken->tokenable_id !== $admin->getKey()
                || $lockedToken->tokenable_type !== $admin->getMorphClass()
                || ! $lockedToken->can('refresh')) {
                return null;
            }

            // A second/concurrent use of a consumed refresh token is a reuse
            // attempt. Revoke the whole session, including any replacement pair.
            if ($lockedToken->is_revoked) {
                $this->revokeSession($lockedToken->session_id);

                return null;
            }

            if ($lockedToken->expires_at !== null && $lockedToken->expires_at->isPast()) {
                return null;
            }

            $sessionId = $lockedToken->session_id ?? (string) Str::uuid();
            $sessionExpiresAt = $lockedToken->expires_at
                ?? now()->addDays($this->refreshTtlDays());

            // Keep consumed token rows so a later replay can be detected.
            $this->revokeTokenPair($sessionId);

            return $this->issueForSession($admin, $sessionId, $sessionExpiresAt);
        });
    }

    /**
     * Revoke a complete login session and its registered push device.
     */
    public function revokeSession(?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        DB::transaction(function () use ($sessionId): void {
            $this->revokeTokenPair($sessionId);

            DeviceToken::query()
                ->where('session_id', $sessionId)
                ->delete();
        });
    }

    /**
     * Mark the consumed pair as revoked while retaining it for reuse detection.
     */
    private function revokeTokenPair(string $sessionId): void
    {
        PersonalAccessToken::query()
            ->where('session_id', $sessionId)
            ->update(['is_revoked' => true]);
    }

    /**
     * Issue a replacement pair inside an existing session's absolute lifetime.
     *
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function issueForSession(
        Admin $admin,
        string $sessionId,
        DateTimeInterface $sessionExpiresAt,
    ): array {
        $accessExpiresAt = now()->addMinutes($this->accessTtlMinutes());

        if ($sessionExpiresAt->getTimestamp() < $accessExpiresAt->getTimestamp()) {
            $accessExpiresAt = $sessionExpiresAt;
        }

        $access = $this->createToken(
            admin: $admin,
            name: 'access',
            abilities: ['access'],
            expiresAt: $accessExpiresAt,
            sessionId: $sessionId,
        );

        $refresh = $this->createToken(
            admin: $admin,
            name: 'refresh',
            abilities: ['refresh'],
            expiresAt: $sessionExpiresAt,
            sessionId: $sessionId,
        );

        return [
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => max(0, $accessExpiresAt->getTimestamp() - now()->getTimestamp()),
        ];
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

        $token->accessToken->forceFill([
            'session_id' => $sessionId,
            'is_revoked' => false,
        ])->save();

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
