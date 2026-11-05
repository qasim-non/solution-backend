<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Request as ProjectRequest;
use App\Notifications\NewProjectRequestNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotificationService
{
    public function __construct(private FirebaseService $firebase) {}

    /**
     * Notify every admin about a newly submitted customer request.
     */
    public function notifyAdminsOfNewRequest(ProjectRequest $projectRequest): void
    {
        try {
            $admins = Admin::query()->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewProjectRequestNotification($projectRequest));
            }

            $tokens = DeviceToken::query()
                ->whereNotNull('admin_id')
                ->pluck('token')
                ->all();

            $this->sendPush(
                $tokens,
                'طلب مشروع جديد',
                'تم استلام طلب جديد: '.$projectRequest->project_name,
                [
                    'type' => 'new_request',
                    'request_id' => $projectRequest->id,
                    'url' => '/dashboard/requests',
                ],
            );
        } catch (Throwable $exception) {
            // Notification failures must not break request creation.
            Log::error('Failed to notify admins about a new request.', [
                'request_id' => $projectRequest->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notify the browser that submitted a request when an admin completes it.
     */
    public function notifyCustomerOfCompletion(ProjectRequest $projectRequest): void
    {
        try {
            if (! $projectRequest->device_id) {
                return;
            }

            $tokens = DeviceToken::query()
                ->where('device_id', $projectRequest->device_id)
                ->pluck('token')
                ->all();

            $this->sendPush(
                $tokens,
                'تم إنجاز طلبك',
                'اكتمل العمل على طلب: '.$projectRequest->project_name,
                [
                    'type' => 'request_completed',
                    'request_id' => $projectRequest->id,
                    'url' => '/',
                ],
            );
        } catch (Throwable $exception) {
            Log::error('Failed to notify a customer about request completion.', [
                'request_id' => $projectRequest->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, scalar>  $data
     */
    private function sendPush(array $tokens, string $title, string $body, array $data): void
    {
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
