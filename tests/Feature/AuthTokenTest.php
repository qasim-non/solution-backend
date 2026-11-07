<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $username = 'admin', string $password = 'secret'): Admin
    {
        return Admin::forceCreate([
            'username' => $username,
            'password' => bcrypt($password),
            'role' => 'dev',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function login(string $username = 'admin', string $password = 'secret'): array
    {
        return $this->postJson('/api/login', compact('username', 'password'))
            ->assertOk()
            ->json();
    }

    /**
     * Send the next request with a token.
     *
     * The auth guard caches its user for the whole PHP process, so it must be
     * reset between requests to validate the token again like a real request.
     */
    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_login_returns_an_access_and_refresh_token_pair(): void
    {
        $this->makeAdmin();

        $response = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertOk();

        $response
            ->assertJsonPath('message', 'Admin login successful.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800)
            ->assertJsonStructure(['token', 'refresh_token']);

        $access = PersonalAccessToken::query()->where('name', 'access')->firstOrFail();
        $refresh = PersonalAccessToken::query()->where('name', 'refresh')->firstOrFail();

        $this->assertNotNull($access->session_id);
        $this->assertSame($access->session_id, $refresh->session_id);

        $this->assertTrue($access->expires_at->between(
            now()->addMinutes(29),
            now()->addMinutes(31),
        ));

        $this->assertTrue($refresh->expires_at->between(
            now()->addDays(29),
            now()->addDays(31),
        ));

        $this->assertSame(['access'], $access->abilities);
        $this->assertSame(['refresh'], $refresh->abilities);
    }

    public function test_the_access_token_authenticates_protected_routes(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertOk();
    }

    public function test_the_refresh_token_cannot_access_protected_routes(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->asToken($pair['refresh_token'])
            ->getJson('/api/dashboard')
            ->assertForbidden();
    }

    public function test_the_access_token_cannot_be_used_to_refresh(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->asToken($pair['token'])
            ->postJson('/api/auth/refresh')
            ->assertForbidden();
    }

    public function test_refreshing_rotates_the_pair_and_revokes_the_old_tokens(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $newPair = $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800)
            ->json();

        // The old pair is revoked.
        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();

        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        // The new pair works.
        $this->asToken($newPair['token'])
            ->getJson('/api/dashboard')
            ->assertOk();

        $this->asToken($newPair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertOk();
    }

    public function test_the_access_token_expires_after_thirty_minutes(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->travel(31)->minutes();

        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();
    }

    public function test_the_refresh_token_expires_after_thirty_days(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->travel(31)->days();

        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_access_and_refresh_tokens(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->asToken($pair['token'])
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Admin logged out successfully.');

        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_refreshing_requires_a_token(): void
    {
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
    }
}
