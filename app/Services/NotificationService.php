<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\DeviceToken;
use App\Models\Message;
use App\Models\Notification as InAppNotification;
use App\Models\Request as ProjectRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(private FirebaseService $firebase) {}

    /**
     * Notify every admin about a newly submitted customer request.
     */
    public function notifyAdminsOfNewRequest(ProjectRequest $projectRequest): void
    {
        $this->notifyAdmins(
            type: 'new_request',
            data: [
                'request_id' => $projectRequest->id,
                'project_name' => $projectRequest->project_name,
                'message' => 'تم استلام طلب مشروع جديد: '.$projectRequest->project_name,
                'url' => '/dashboard/requests',
            ],
            title: 'طلب مشروع جديد',
            body: 'تم استلام طلب جديد: '.$projectRequest->project_name,
            context: ['request_id' => $projectRequest->id],
        );
    }

    /**
     * Notify every admin about a newly submitted contact message.
     */
    public function notifyAdminsOfNewMessage(Message $message): void
    {
        $this->notifyAdmins(
            type: 'new_message',
            data: [
                'message_id' => $message->id,
                'email' => $message->email,
                'message' => $message->text_message,
                'url' => '/dashboard/messages',
            ],
            title: 'رسالة جديدة',
            body: 'تم استلام رسالة جديدة: '.$message->full_name,
            context: ['message_id' => $message->id],
        );
    }

    /**
     * Store one system-wide in-app notification and queue the admin push.
     *
     * Notifications are shared by every admin: one row, and the first admin to
     * read it marks it read for the whole team.
     *
     * @param  array<string, scalar>  $data  Payload shared with the frontend; `type` is added here.
     * @param  array<string, scalar>  $context  Extra fields for the error log.
     */
    private function notifyAdmins(
        string $type,
        array $data,
        string $title,
        string $body,
        array $context = [],
    ): void {
        $data = ['type' => $type, ...$data];

        try {
            InAppNotification::create([
                'type' => $type,
                'data' => $data,
            ]);

            $this->sendPush($title, $body, $data);
        } catch (Throwable $exception) {
            // Notification failures must never break the customer-facing action.
            Log::error('Failed to notify admins.', [
                'type' => $type,
                ...$context,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send a push to every registered admin device.
     *
     * Fetching the tokens here keeps every notification flow from repeating the
     * same query, and keeps the audience (all admins) in one place.
     *
     * @param  array<string, scalar>  $data
     */
    private function sendPush(string $title, string $body, array $data): void
    {
        $tokens = DeviceToken::query()->pluck('token')->all();
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return;
        }

        if (! $this->firebase->isConfigured()) {
            Log::debug('Firebase is not configured; skipping push notification.', [
                'type' => $data['type'] ?? null,
                'tokens' => count($tokens),
            ]);

            return;
        }

        SendPushNotification::dispatch($tokens, $title, $body, $data);
    }
}
