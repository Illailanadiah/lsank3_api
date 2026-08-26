<?php

namespace App\Services\Notifications\Channels;

use App\Jobs\Notifications\SendPushNotificationJob;
use App\Models\LsankNotification;
use App\Models\LsankUserDevice;
use Illuminate\Support\Collection;

class PushNotificationChannel implements NotificationChannel
{
    use CreatesNotificationDeliveries;

    public function name(): string
    {
        return 'push';
    }

    public function dispatch(
        LsankNotification $notification,
        array $recipient,
        array $message,
        array $context = []
    ): Collection {
        $userId = (int) (
            $recipient['user_id']
            ?? data_get(
                $recipient,
                'user.user_id'
            )
            ?? data_get(
                $recipient,
                'user.id'
            )
            ?? $notification->user_id
            ?? 0
        );

        /*
         * Allow direct token for special cases/tests.
         */
        $directToken = trim(
            (string) (
                $recipient['fcm_token']
                ?? ''
            )
        );

        if ($directToken !== '') {
            $tokens = collect([
                $directToken,
            ]);
        } elseif ($userId > 0) {
            $tokens =
                LsankUserDevice::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'fcm_token'
                )
                ->pluck(
                    'fcm_token'
                )
                ->map(
                    fn($token) =>
                    trim(
                        (string) $token
                    )
                )
                ->filter()
                ->unique()
                ->values();
        } else {
            $tokens = collect();
        }

        if ($tokens->isEmpty()) {
            return collect();
        }

        $payload =
            $this->buildDeliveryPayload(
                $notification,
                $message,
                $context
            );

        $deliveries = collect();

        foreach ($tokens as $token) {
            $delivery =
                $this->createDelivery(
                    $notification,
                    $this->name(),
                    $token,
                    $payload,
                    'fcm'
                );

            if (
                !$this->deliveryAlreadyCompleted(
                    $delivery
                )
            ) {
                SendPushNotificationJob::dispatch(
                    (int) $delivery->delivery_id
                )->afterCommit();
            }

            $deliveries->push(
                $delivery
            );
        }

        return $deliveries;
    }
}