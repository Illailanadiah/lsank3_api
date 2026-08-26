<?php

namespace App\Services\Notifications\Channels;

use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\LsankNotification;
use Illuminate\Support\Collection;

class WhatsAppNotificationChannel implements NotificationChannel
{
    use CreatesNotificationDeliveries;

    public function name(): string
    {
        return 'whatsapp';
    }

    public function dispatch(
        LsankNotification $notification,
        array $recipient,
        array $message,
        array $context = []
    ): Collection {
        $rawPhone = (string) (
            $recipient['whatsapp']
            ?? $recipient['phone']
            ?? $recipient['phone_no']
            ?? data_get(
                $recipient,
                'user.phone'
            )
            ?? data_get(
                $recipient,
                'user.phone_no'
            )
            ?? ''
        );

        $phone =
            $this->normalizeMalaysiaPhone(
                $rawPhone
            );

        if ($phone === null) {
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
                $phone,
                $payload,
                'meta_whatsapp'
            );

        if (
            !$this->deliveryAlreadyCompleted(
                $delivery
            )
        ) {
            SendWhatsAppNotificationJob::dispatch(
                (int) $delivery->delivery_id
            )->afterCommit();
        }

        return collect([
            $delivery,
        ]);
    }

    /**
     * Normalize Malaysian number into:
     *
     * 60123456789
     *
     * Examples:
     *
     * 0123456789
     * -> 60123456789
     *
     * +60123456789
     * -> 60123456789
     */
    private function normalizeMalaysiaPhone(
        string $phone
    ): ?string {
        $digits = preg_replace(
            '/\D+/',
            '',
            trim($phone)
        );

        if (!$digits) {
            return null;
        }

        if (
            str_starts_with(
                $digits,
                '0'
            )
        ) {
            $digits =
                '60'
                . substr(
                    $digits,
                    1
                );
        } elseif (
            str_starts_with(
                $digits,
                '1'
            )
        ) {
            /*
             * Example:
             * 123456789
             * -> 60123456789
             */
            $digits =
                '60'
                . $digits;
        }

        /*
         * Basic E.164 length validation.
         */
        if (
            strlen($digits) < 10
            || strlen($digits) > 15
        ) {
            return null;
        }

        return $digits;
    }
}