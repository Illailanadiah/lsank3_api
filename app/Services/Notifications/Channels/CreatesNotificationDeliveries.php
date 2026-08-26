<?php

namespace App\Services\Notifications\Channels;

use App\Models\LsankNotification;
use App\Models\LsankNotificationDelivery;

trait CreatesNotificationDeliveries
{
    /**
     * Create one idempotent delivery record.
     *
     * delivery_key prevents duplicate delivery for:
     *
     * notification + channel + recipient
     */
    protected function createDelivery(
        LsankNotification $notification,
        string $channel,
        string $recipient,
        array $payload,
        ?string $provider = null,
        string $status = 'pending',
        array $extra = []
    ): LsankNotificationDelivery {
        $recipient = trim($recipient);

        $deliveryKey = $this->makeDeliveryKey(
            (int) $notification->notification_id,
            $channel,
            $recipient
        );

        $attributes = [
            'notification_id' =>
                (int) $notification->notification_id,

            'channel' =>
                $channel,

            'recipient' =>
                $recipient !== ''
                    ? $recipient
                    : null,

            'status' =>
                $status,

            'provider' =>
                $provider,

            'provider_message_id' =>
                null,

            'attempt_count' =>
                0,

            'payload' =>
                $payload,

            'last_error' =>
                null,

            'queued_at' =>
                now(),

            'processing_at' =>
                null,

            'sent_at' =>
                null,

            'delivered_at' =>
                null,

            'failed_at' =>
                null,

            'next_attempt_at' =>
                null,
        ];

        $attributes = array_merge(
            $attributes,
            $extra
        );

        return LsankNotificationDelivery::query()
            ->firstOrCreate(
                [
                    'delivery_key' =>
                        $deliveryKey,
                ],
                $attributes
            );
    }

    /**
     * Build delivery key.
     */
    protected function makeDeliveryKey(
        int $notificationId,
        string $channel,
        string $recipient
    ): string {
        return hash(
            'sha256',
            implode(
                '|',
                [
                    $notificationId,
                    strtolower(
                        trim($channel)
                    ),
                    strtolower(
                        trim($recipient)
                    ),
                ]
            )
        );
    }

    /**
     * Standard payload shared by all channels.
     */
    protected function buildDeliveryPayload(
        LsankNotification $notification,
        array $message,
        array $context = []
    ): array {
        $notificationMetadata =
            is_array($notification->metadata ?? null)
                ? $notification->metadata
                : [];

        $messageMetadata =
            is_array($message['metadata'] ?? null)
                ? $message['metadata']
                : [];

        return [
            'notification_id' =>
                (int) $notification->notification_id,

            'event_type' =>
                $notification->event_type
                ?? $message['event_type']
                ?? null,

            'audience' =>
                $notification->audience
                ?? $message['audience']
                ?? null,

            'title' =>
                $message['title']
                ?? $notification->title
                ?? 'Notifikasi LSANK',

            'subject' =>
                $message['subject']
                ?? $message['title']
                ?? $notification->title
                ?? 'Notifikasi LSANK',

            'body' =>
                $message['body']
                ?? $notification->message
                ?? '',

            'action_url' =>
                $message['action_url']
                ?? $notification->action_url
                ?? null,

            'action_label' =>
                $message['action_label']
                ?? $notification->action_label
                ?? null,

            'language' =>
                $message['language']
                ?? 'ms',

            'severity' =>
                $notification->severity
                ?? $message['severity']
                ?? 'info',

            'priority' =>
                (int) (
                    $notification->priority
                    ?? $message['priority']
                    ?? 3
                ),

            'metadata' =>
                array_merge(
                    $notificationMetadata,
                    $messageMetadata
                ),

            /*
             * Push data.
             */
            'data' =>
                is_array($message['data'] ?? null)
                    ? $message['data']
                    : [],

            /*
             * WhatsApp provider information.
             */
            'provider_template_name' =>
                $message[
                    'provider_template_name'
                ]
                ?? null,

            'provider_parameters' =>
                is_array(
                    $message[
                        'provider_parameters'
                    ]
                    ?? null
                )
                    ? $message[
                        'provider_parameters'
                    ]
                    : [],
        ];
    }

    /**
     * Check whether delivery has already completed.
     */
    protected function deliveryAlreadyCompleted(
        LsankNotificationDelivery $delivery
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string) $delivery->status
                )
            ),
            [
                'sent',
                'delivered',
                'skipped',
            ],
            true
        );
    }
}