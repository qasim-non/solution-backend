<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeviceToken;
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
            ->json('data');
    }

    /**
     * Send the next request with a token and force Sanctum to authenticate it again.
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
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 1800)
            ->assertJsonStructure(['data' => ['token', 'refresh_token']]);

        $access = PersonalAccessToken::query()->where('name', 'access')->firstOrFail();
        $refresh = PersonalAccessToken::query()->where('name', 'refresh')->firstOrFail();

        $this->assertNotNull($access->session_id);
        $this->assertSame($access->session_id, $refresh->session_id);
        $this->assertFalse($access->is_revoked);
        $this->assertFalse($refresh->is_revoked);

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

    public function test_refreshing_rotates_tokens_and_reuse_revokes_the_compromised_session(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $oldRefresh = PersonalAccessToken::findToken($pair['refresh_token']);
        $oldAccess = PersonalAccessToken::findToken($pair['token']);

        $newPair = $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed successfully.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 1800)
            ->json('data');

        $this->assertTrue($oldRefresh->fresh()->is_revoked);
        $this->assertTrue($oldAccess->fresh()->is_revoked);

        // The old access token is rejected, but does not by itself revoke the
        // replacement session.
        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        $this->asToken($newPair['token'])
            ->getJson('/api/dashboard')
            ->assertOk();

        // Replaying the consumed refresh token is treated as possible theft.
        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();

        // The entire session, including the attacker's replacement pair, is dead.
        $this->asToken($newPair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        $this->asToken($newPair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_refresh_rotation_does_not_extend_the_absolute_session_expiration(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $originalExpiry = PersonalAccessToken::findToken($pair['refresh_token'])->expires_at;

        $this->travel(10)->days();

        $newPair = $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertOk()
            ->json('data');

        $newRefresh = PersonalAccessToken::findToken($newPair['refresh_token']);

        $this->assertSame(
            $originalExpiry->toDateTimeString(),
            $newRefresh->expires_at->toDateTimeString(),
        );
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

    public function test_the_refresh_token_expires_after_thirty_days_from_login(): void
    {
        $this->makeAdmin();
        $pair = $this->login();

        $this->travel(31)->days();

        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_session_and_deletes_its_device_token(): void
    {
        $admin = $this->makeAdmin();
        $pair = $this->login();

        $this->asToken($pair['token'])
            ->postJson('/api/device-tokens', [
                'token' => 'fcm-device-a',
                'platform' => 'web',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-device-a',
            'session_id' => PersonalAccessToken::findToken($pair['token'])->session_id,
        ]);

        $this->asToken($pair['token'])
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Admin logged out successfully.');

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-device-a']);

        $this->asToken($pair['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        $this->asToken($pair['refresh_token'])
            ->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_logging_out_one_device_does_not_revoke_another_device_session(): void
    {
        $this->makeAdmin();

        $firstDevice = $this->login();
        $secondDevice = $this->login();

        $this->asToken($firstDevice['token'])
            ->postJson('/api/device-tokens', ['token' => 'fcm-device-one', 'platform' => 'android'])
            ->assertCreated();

        $this->asToken($secondDevice['token'])
            ->postJson('/api/device-tokens', ['token' => 'fcm-device-two', 'platform' => 'ios'])
            ->assertCreated();

        $this->asToken($firstDevice['token'])
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-device-one']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-device-two']);

        $this->asToken($firstDevice['token'])
            ->getJson('/api/dashboard')
            ->assertUnauthorized();

        $this->asToken($secondDevice['token'])
            ->getJson('/api/dashboard')
            ->assertOk();
    }

    public function test_refreshing_requires_a_token(): void
    {
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
    }
}
