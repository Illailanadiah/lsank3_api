<?php

namespace App\Services\Notifications\Channels;

use App\Jobs\Notifications\SendEmailNotificationJob;
use App\Models\LsankNotification;
use Illuminate\Support\Collection;

class EmailNotificationChannel implements NotificationChannel
{
    use CreatesNotificationDeliveries;

    public function name(): string
    {
        return 'email';
    }

    public function dispatch(
        LsankNotification $notification,
        array $recipient,
        array $message,
        array $context = []
    ): Collection {
        $email = strtolower(
            trim(
                (string) (
                    $recipient['email']
                    ?? data_get(
                        $recipient,
                        'user.email'
                    )
                    ?? ''
                )
            )
        );

        if (
            $email === ''
            || !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return collect();
        }

        $payload =
            $this->buildDeliveryPayload(
                $notification,
                $message,
                $context
            );

        $delivery =
            $this->createDelivery(
                $notification,
                $this->name(),
                $email,
                $payload,
                'smtp'
            );

        /*
         * Do not queue duplicate jobs when the
         * delivery has already completed.
         */
        if (
            !$this->deliveryAlreadyCompleted(
                $delivery
            )
        ) {
            SendEmailNotificationJob::dispatch(
                (int) $delivery->delivery_id
            )->afterCommit();
        }

        return collect([
            $delivery,
        ]);
    }
}