<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceTokenRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * Register (or refresh) a browser/device FCM token.
     *
     * The route is public so anonymous customers can opt in to notifications.
     * When a valid Sanctum bearer token is present the token is linked to the
     * admin, which is how the dashboard receives "new request" pushes.
     */
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $admin = Auth::guard('sanctum')->user();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'admin_id' => $admin?->id,
                'device_id' => $validated['device_id'],
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
     * Remove a token when the user disables notifications or logs out.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()->where('token', $validated['token'])->delete();

        return $this->success(null, 'Device token removed successfully.');
    }
}
