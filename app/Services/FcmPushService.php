<?php

namespace App\Services;

use App\Models\LsankDeviceToken;
use App\Models\LsankUser;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FcmPushService
{
    public function __construct(
        private readonly Messaging $messaging
    ) {
    }

    /**
     * Send a notification to every active device belonging to one user.
     */
    public function sendToUser(
        LsankUser $user,
        string $title,
        string $body,
        array $data = []
    ): array {
        $tokens = $user->deviceTokens()
            ->where('is_active', true)
            ->get();

        return $this->sendToDeviceTokens(
            $tokens,
            $title,
            $body,
            $data
        );
    }

    /**
     * Send a notification to multiple users.
     */
    public function sendToUsers(
        iterable $users,
        string $title,
        string $body,
        array $data = []
    ): array {
        $summary = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'deactivated' => 0,
        ];

        foreach ($users as $user) {
            if (!$user instanceof LsankUser) {
                continue;
            }

            $result = $this->sendToUser(
                $user,
                $title,
                $body,
                $data
            );

            foreach ($summary as $key => $value) {
                $summary[$key] += $result[$key] ?? 0;
            }
        }

        return $summary;
    }

    /**
     * Send to selected database token records.
     */
    public function sendToDeviceTokens(
        iterable $deviceTokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        $result = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'deactivated' => 0,
        ];

        $payload = $this->normaliseData($data);

        foreach ($deviceTokens as $deviceToken) {
            if (
                !$deviceToken instanceof LsankDeviceToken ||
                !$deviceToken->is_active
            ) {
                continue;
            }

            $result['total']++;

            try {
                $message = CloudMessage::new()
                    ->toToken($deviceToken->token)
                    ->withNotification(
                        Notification::create($title, $body)
                    )
                    ->withData($payload)
                    ->withAndroidConfig(
                        AndroidConfig::fromArray([
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'lsank_notifications',
                                'sound' => 'default',
                                'default_sound' => true,
                            ],
                        ])
                    );

                $this->messaging->send($message);

                $deviceToken->forceFill([
                    'last_used_at' => now(),
                ])->save();

                $result['sent']++;
            } catch (Throwable $exception) {
                $result['failed']++;

                if ($this->isInvalidTokenException($exception)) {
                    $deviceToken->forceFill([
                        'is_active' => false,
                    ])->save();

                    $result['deactivated']++;
                }

                Log::warning('FCM notification failed.', [
                    'device_token_id' =>
                        $deviceToken->device_token_id,
                    'user_id' => $deviceToken->user_id,
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Firebase data payload values must be strings.
     */
    private function normaliseData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(function ($value, $key): array {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif (is_array($value) || is_object($value)) {
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );
                } elseif ($value === null) {
                    $value = '';
                }

                return [(string) $key => (string) $value];
            })
            ->all();
    }

    private function isInvalidTokenException(
        Throwable $exception
    ): bool {
        $message = strtolower($exception->getMessage());

        return str_contains(
            $message,
            'registration-token-not-registered'
        ) ||
            str_contains($message, 'requested entity was not found') ||
            str_contains($message, 'invalid registration token') ||
            str_contains($message, 'invalid-registration-token') ||
            str_contains($message, 'unregistered');
    }
}