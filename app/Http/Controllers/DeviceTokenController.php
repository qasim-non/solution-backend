<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceTokenRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * Register (or refresh) the authenticated admin's FCM token.
     *
     * Only admins can receive push notifications, so the route is protected by
     * `auth:sanctum` and every token is linked to the admin that registered it.
     */
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'admin_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'web',
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ],
        );

        return $this->success(
            ['id' => $deviceToken->id],
            'Device token registered successfully.',
            201,
        );
    }

    /**
     * Remove one of the authenticated admin's tokens when notifications are
     * disabled or the admin logs out.
     */
    public function destroy(DeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DeviceToken::query()
            ->where('admin_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return $this->success(null, 'Device token removed successfully.');
    }
}
