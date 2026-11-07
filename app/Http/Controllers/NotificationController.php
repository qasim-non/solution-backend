<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Paginated in-app notifications.
     *
     * Notifications are system-wide, so every authenticated admin receives the
     * same list. `unread_count` is included so the notification page does not
     * need a second request to render the badge.
     */
    public function index(): JsonResponse
    {
        $notifications = Notification::query()
            ->latest()
            ->paginate(20);

        return $this->paginated($notifications, 'Notifications retrieved successfully.', [
            'unread_count' => $this->unreadNotificationsCount(),
        ]);
    }

    /**
     * Lightweight endpoint for the sidebar badge.
     *
     * The admin layout calls this once on load and refreshes it after a push or
     * after marking notifications as read. It avoids loading the full list just
     * to display a number.
     */
    public function unreadCount(): JsonResponse
    {
        return $this->success(
            ['unread_count' => $this->unreadNotificationsCount()],
            'Unread notification count retrieved successfully.',
        );
    }

    /**
     * Mark one notification as read for every admin.
     *
     * The first admin wins; later requests keep the original `read_by`.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->read_at === null) {
            $notification->forceFill([
                'read_at' => now(),
                'read_by' => $request->user()?->id,
            ])->save();
        }

        return $this->success(null, 'Notification marked as read.');
    }

    /**
     * Mark every unread notification as read for all admins.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::query()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'read_by' => $request->user()?->id,
            ]);

        return $this->success(null, 'All notifications marked as read.');
    }

    /**
     * Shared unread count for every admin.
     */
    private function unreadNotificationsCount(): int
    {
        return Notification::query()->whereNull('read_at')->count();
    }
}
