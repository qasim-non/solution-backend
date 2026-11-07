<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceTokenRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * Register (or refresh) the authenticated admin's FCM token.
     */
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentToken = $request->user()->currentAccessToken();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'admin_id' => $request->user()->id,
                'session_id' => $currentToken instanceof PersonalAccessToken
                    ? $currentToken->session_id
                    : null,
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
     * Remove one of the authenticated admin's own device tokens.
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
