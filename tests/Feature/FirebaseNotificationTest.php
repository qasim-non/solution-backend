<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Request as ProjectRequest;
use App\Models\SystemType;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FirebaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $username = 'test-admin'): Admin
    {
        return Admin::forceCreate([
            'username' => $username,
            'password' => bcrypt('secret'),
            'role' => 'dev',
        ]);
    }

    public function test_device_token_registration_requires_authentication(): void
    {
        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-123',
            'platform' => 'web',
        ])->assertUnauthorized();
    }

    public function test_device_token_removal_requires_authentication(): void
    {
        $this->deleteJson('/api/device-tokens', ['token' => 'fcm-token-123'])
            ->assertUnauthorized();
    }

    public function test_an_authenticated_admin_can_register_a_device_token(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/device-tokens', [
                'token' => 'fcm-token-123',
                'platform' => 'web',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-token-123',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_registering_the_same_token_twice_updates_the_existing_row(): void
    {
        $admin = $this->makeAdmin();

        $payload = [
            'token' => 'fcm-token-123',
            'platform' => 'web',
        ];

        $this->actingAs($admin, 'sanctum')->postJson('/api/device-tokens', $payload)->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson('/api/device-tokens', $payload)->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_device_token_registration_validates_the_token(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/device-tokens', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_an_admin_can_remove_their_own_device_token(): void
    {
        $admin = $this->makeAdmin();

        DeviceToken::create([
            'admin_id' => $admin->id,
            'token' => 'fcm-token-123',
            'platform' => 'web',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/device-tokens', ['token' => 'fcm-token-123'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-123']);
    }

    public function test_an_admin_cannot_remove_another_admins_device_token(): void
    {
        $admin = $this->makeAdmin('admin-one');
        $otherAdmin = $this->makeAdmin('admin-two');

        DeviceToken::create([
            'admin_id' => $otherAdmin->id,
            'token' => 'other-admin-token',
            'platform' => 'web',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/device-tokens', ['token' => 'other-admin-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['token' => 'other-admin-token']);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_a_customer_request_notifies_admins_without_storing_a_device_id(): void
    {
        Queue::fake();

        $this->mock(FirebaseService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $admin = $this->makeAdmin();

        DeviceToken::create([
            'admin_id' => $admin->id,
            'token' => 'admin-token-123',
            'platform' => 'web',
        ]);

        $systemType = SystemType::create(['name' => 'Web']);

        $response = $this->postJson('/api/request', [
            'project_name' => 'Test Project',
            'mobile' => '+966500000000',
            'description' => 'A test request.',
            'system_types' => [$systemType->id],
        ]);

        $response->assertCreated();

        $this->assertFalse(Schema::hasColumn('requests', 'device_id'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Admin::class,
            'notifiable_id' => $admin->id,
        ]);

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job): bool {
            return ($job->data['type'] ?? null) === 'new_request'
                && $job->tokens === ['admin-token-123'];
        });
    }

    public function test_completing_a_request_does_not_dispatch_a_customer_push(): void
    {
        Queue::fake();

        $admin = $this->makeAdmin();

        $projectRequest = ProjectRequest::create([
            'project_name' => 'Test Project',
            'mobile' => '+966500000000',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/request/{$projectRequest->id}/complete")
            ->assertOk();

        $this->assertSame('completed', $projectRequest->fresh()->status);

        Queue::assertNothingPushed();
    }
}
