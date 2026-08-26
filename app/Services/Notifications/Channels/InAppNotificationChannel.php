<?php

namespace App\Services\Notifications\Channels;

use App\Models\LsankNotification;
use Illuminate\Support\Collection;

class InAppNotificationChannel implements NotificationChannel
{
    use CreatesNotificationDeliveries;

    public function name(): string
    {
        return 'in_app';
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

        if ($userId <= 0) {
            return collect();
        }

        $payload =
            $this->buildDeliveryPayload(
                $notification,
                $message,
                $context
            );

        /*
         * lsank_notifications itself is the in-app
         * source of truth.
         *
         * The delivery row records that the notification
         * has been made available to the recipient.
         */
        $delivery =
            $this->createDelivery(
                $notification,
                $this->name(),
                (string) $userId,
                $payload,
                'lsank',
                'delivered',
                [
                    'sent_at' => now(),
                    'delivered_at' => now(),
                ]
            );

        return collect([
            $delivery,
        ]);
    }
}