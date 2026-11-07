<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Request as ProjectRequest;
use App\Models\SystemType;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
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

    /**
     * Authenticate with an access-ability token, matching production tokens.
     */
    private function authenticate(Admin $admin): self
    {
        Sanctum::actingAs($admin, ['access']);

        return $this;
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

        $this->authenticate($admin)
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

        $this->authenticate($admin)->postJson('/api/device-tokens', $payload)->assertCreated();
        $this->authenticate($admin)->postJson('/api/device-tokens', $payload)->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_device_token_registration_validates_the_token(): void
    {
        $admin = $this->makeAdmin();

        $this->authenticate($admin)
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

        $this->authenticate($admin)
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

        $this->authenticate($admin)
            ->deleteJson('/api/device-tokens', ['token' => 'other-admin-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['token' => 'other-admin-token']);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_a_customer_request_creates_one_system_wide_notification(): void
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
        $this->assertFalse(Schema::hasColumn('notifications', 'notifiable_id'));

        $notification = Notification::query()->sole();

        $this->assertSame('new_request', $notification->type);
        $this->assertSame('Test Project', $notification->data['project_name']);
        $this->assertNull($notification->read_at);

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job): bool {
            return ($job->data['type'] ?? null) === 'new_request'
                && $job->tokens === ['admin-token-123'];
        });
    }

    public function test_every_admin_sees_the_same_notifications(): void
    {
        $firstAdmin = $this->makeAdmin('admin-one');
        $secondAdmin = $this->makeAdmin('admin-two');

        Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 1],
        ]);

        $this->authenticate($firstAdmin)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread_count', 1);

        $this->authenticate($secondAdmin)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_the_unread_count_endpoint_returns_the_shared_count(): void
    {
        $admin = $this->makeAdmin();

        Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 1],
        ]);

        Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 2],
        ]);

        $this->authenticate($admin)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_marking_a_missing_notification_returns_not_found(): void
    {
        $admin = $this->makeAdmin();

        $this->authenticate($admin)
            ->patchJson('/api/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertNotFound();
    }

    public function test_reading_a_notification_marks_it_read_for_every_admin(): void
    {
        $firstAdmin = $this->makeAdmin('admin-one');
        $secondAdmin = $this->makeAdmin('admin-two');

        $notification = Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 1],
        ]);

        $this->authenticate($firstAdmin)
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $notification->refresh();

        $this->assertNotNull($notification->read_at);
        $this->assertSame($firstAdmin->id, $notification->read_by);

        $this->authenticate($secondAdmin)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.read_at', fn ($value): bool => $value !== null)
            ->assertJsonPath('unread_count', 0);

        $this->authenticate($secondAdmin)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_all_as_read_marks_every_notification_read(): void
    {
        $firstAdmin = $this->makeAdmin('admin-one');
        $secondAdmin = $this->makeAdmin('admin-two');

        Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 1],
        ]);

        Notification::create([
            'type' => 'new_request',
            'data' => ['type' => 'new_request', 'request_id' => 2],
        ]);

        $this->authenticate($firstAdmin)
            ->patchJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, Notification::query()->whereNull('read_at')->count());
        $this->assertSame(2, Notification::query()->where('read_by', $firstAdmin->id)->count());

        $this->authenticate($secondAdmin)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 0);

        $this->authenticate($secondAdmin)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
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

        $this->authenticate($admin)
            ->patchJson("/api/request/{$projectRequest->id}/complete")
            ->assertOk();

        $this->assertSame('completed', $projectRequest->fresh()->status);

        Queue::assertNothingPushed();
    }
}
