<?php

namespace App\Notifications;

use App\Models\Request as ProjectRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification stored in the `notifications` table for admins.
 *
 * FCM delivery is handled separately by NotificationService so the dashboard
 * still has a history even when push is unavailable.
 */
class NewProjectRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public ProjectRequest $projectRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_request',
            'request_id' => $this->projectRequest->id,
            'project_name' => $this->projectRequest->project_name,
            'message' => 'تم استلام طلب مشروع جديد: '.$this->projectRequest->project_name,
            'url' => '/dashboard/requests',
        ];
    }
}
