<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Request as ProjectRequest;
use App\Models\SystemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirebaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_token_can_be_registered(): void
    {
        $response = $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-123',
            'device_id' => 'device-abc',
            'platform' => 'web',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'fcm-token-123',
            'device_id' => 'device-abc',
            'admin_id' => null,
        ]);
    }

    public function test_registering_the_same_token_twice_updates_the_existing_row(): void
    {
        $payload = [
            'token' => 'fcm-token-123',
            'device_id' => 'device-abc',
            'platform' => 'web',
        ];

        $this->postJson('/api/device-tokens', $payload)->assertCreated();
        $this->postJson('/api/device-tokens', $payload)->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_a_device_token_is_linked_to_the_authenticated_admin(): void
    {
        $admin = Admin::forceCreate([
            'username' => 'test-admin',
            'password' => bcrypt('secret'),
            'role' => 'dev',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/device-tokens', [
                'token' => 'admin-token-123',
                'device_id' => 'admin-device',
                'platform' => 'web',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'admin-token-123',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_device_token_registration_validates_required_fields(): void
    {
        $this->postJson('/api/device-tokens', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'device_id']);
    }

    public function test_a_device_token_can_be_removed(): void
    {
        DeviceToken::create([
            'token' => 'fcm-token-123',
            'device_id' => 'device-abc',
            'platform' => 'web',
        ]);

        $this->deleteJson('/api/device-tokens', ['token' => 'fcm-token-123'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-123']);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_a_customer_request_stores_the_device_id_and_notifies_admins(): void
    {
        $admin = Admin::forceCreate([
            'username' => 'test-admin',
            'password' => bcrypt('secret'),
            'role' => 'dev',
        ]);

        $systemType = SystemType::create(['name' => 'Web']);

        $response = $this->postJson('/api/request', [
            'project_name' => 'Test Project',
            'mobile' => '+966500000000',
            'description' => 'A test request.',
            'device_id' => 'device-abc',
            'system_types' => [$systemType->id],
        ]);

        $response->assertCreated();

        $projectRequest = ProjectRequest::where('project_name', 'Test Project')->firstOrFail();

        $this->assertSame('device-abc', $projectRequest->device_id);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Admin::class,
            'notifiable_id' => $admin->id,
        ]);
    }

    public function test_completing_a_request_marks_it_as_completed(): void
    {
        $admin = Admin::forceCreate([
            'username' => 'test-admin',
            'password' => bcrypt('secret'),
            'role' => 'dev',
        ]);

        $projectRequest = ProjectRequest::create([
            'project_name' => 'Test Project',
            'mobile' => '+966500000000',
            'device_id' => 'device-abc',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/request/{$projectRequest->id}/complete")
            ->assertOk();

        $this->assertSame('completed', $projectRequest->fresh()->status);
    }
}
