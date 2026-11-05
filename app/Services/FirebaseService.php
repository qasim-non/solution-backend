<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\WebPushConfig;
use RuntimeException;

/**
 * Thin wrapper around the Firebase Admin SDK.
 *
 * The wrapper keeps the rest of the application independent from the SDK and
 * makes the "Firebase is not configured yet" case explicit instead of letting
 * a missing service account break customer requests.
 */
class FirebaseService
{
    private ?Messaging $messaging = null;

    /**
     * Whether a service account is available.
     */
    public function isConfigured(): bool
    {
        return $this->resolveCredentials() !== null;
    }

    /**
     * Send a notification to a list of FCM registration tokens.
     *
     * @param  array<int, string>  $tokens
     * @param  array<string, scalar>  $data
     * @return array{sent: int, failed: int, invalid_tokens: array<int, string>, skipped: int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === [] || ! $this->isConfigured()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
                'skipped' => count($tokens),
            ];
        }

        $data = array_map(static fn ($value): string => (string) $value, $data);

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withData($data)
            ->withWebPushConfig($this->webPushConfig($data));

        $report = $this->messaging()->sendMulticast($message, $tokens);

        return [
            'sent' => count($report->successes()->getItems()),
            'failed' => count($report->failures()->getItems()),
            'invalid_tokens' => array_values(array_unique(array_merge(
                $report->invalidTokens(),
                $report->unknownTokens(),
            ))),
            'skipped' => 0,
        ];
    }

    /**
     * Return a configured Messaging instance.
     */
    public function messaging(): Messaging
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        $credentials = $this->resolveCredentials();

        if ($credentials === null) {
            throw new RuntimeException('Firebase service account credentials are not configured.');
        }

        $factory = (new Factory)->withServiceAccount($credentials);

        if ($projectId = config('firebase.project_id')) {
            $factory = $factory->withProjectId($projectId);
        }

        return $this->messaging = $factory->createMessaging();
    }

    /**
     * @return string|array<string, mixed>|null
     */
    private function resolveCredentials(): string|array|null
    {
        $inlineJson = config('firebase.credentials_json');

        if (is_string($inlineJson) && trim($inlineJson) !== '') {
            $decoded = json_decode($inlineJson, true);

            if (! is_array($decoded)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS_JSON does not contain valid JSON.');
            }

            return $decoded;
        }

        $path = config('firebase.credentials');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        // Support paths relative to the Laravel base path, e.g. "storage/app/firebase/...".
        $relativePath = base_path($path);

        return is_file($relativePath) ? $relativePath : null;
    }

    /**
     * Web push options: high urgency for transactional notifications and a
     * click target so the browser focuses the right page.
     *
     * @param  array<string, string>  $data
     */
    private function webPushConfig(array $data): WebPushConfig
    {
        $frontendUrl = rtrim((string) config('firebase.frontend_url'), '/');
        $path = $data['url'] ?? '/';

        $config = [
            'notification' => [
                'icon' => $frontendUrl.'/favicon.ico',
                'badge' => $frontendUrl.'/favicon.ico',
            ],
        ];

        if ($frontendUrl !== '') {
            $config['fcm_options'] = ['link' => $frontendUrl.$path];
        }

        return WebPushConfig::fromArray($config)->withHighUrgency();
    }
}
