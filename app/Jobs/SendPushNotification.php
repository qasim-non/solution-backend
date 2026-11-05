<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends an FCM notification outside the HTTP request lifecycle.
 *
 * Push delivery is a best-effort side effect: a failure here must never roll
 * back a customer request or an admin action.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, scalar>  $data
     */
    public function __construct(
        public array $tokens,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        try {
            $result = $firebase->sendToTokens(
                $this->tokens,
                $this->title,
                $this->body,
                $this->data,
            );

            if ($result['invalid_tokens'] !== []) {
                // Tokens can become invalid when a browser is uninstalled or
                // the user clears site data. Remove them so we stop retrying.
                DeviceToken::whereIn('token', $result['invalid_tokens'])->delete();
            }
        } catch (Throwable $exception) {
            Log::error('Failed to send FCM notification.', [
                'message' => $exception->getMessage(),
                'tokens' => count($this->tokens),
                'data' => $this->data,
            ]);

            throw $exception;
        }
    }
}
