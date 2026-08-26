<?php

namespace App\Services\Notifications;

use App\Models\LsankNotification;
use App\Models\LsankNotificationDelivery;
use App\Models\LsankUserDevice;
use App\Jobs\Notifications\SendEmailNotificationJob;
use App\Jobs\Notifications\SendPushNotificationJob;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use Illuminate\Support\Facades\DB;

class NotificationManager
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly NotificationTemplateService $templateService,
        private readonly NotificationChannelPolicyService $channelPolicy
    ) {
    }

    public function dispatch(
        string $eventType,
        array $context
    ): array {
        $sourceType = trim(
            (string) ($context['source_type'] ?? '')
        );

        $sourceId = (int) ($context['source_id'] ?? 0);

        if ($sourceType === '' || $sourceId <= 0) {
            throw new \InvalidArgumentException(
                'source_type and source_id are required.'
            );
        }

        $recipients = $this->recipientResolver->resolve(
            $eventType,
            $context
        );

        $result = [];

        foreach ($recipients as $recipient) {
            $userId = (int) $recipient['user_id'];
            $audience = (string) $recipient['audience'];

            $templates = $this->templateService->templatesFor(
                $eventType,
                $audience
            );

            if ($templates->isEmpty()) {
                continue;
            }

            $baseTemplate =
                $templates->firstWhere('channel', 'in_app')
                ?? $templates->first();

            $baseRendered = $this->templateService->render(
                $baseTemplate,
                $context
            );

            $eventKey = sprintf(
                '%s:%s:%d:%s:%d',
                $eventType,
                $sourceType,
                $sourceId,
                $audience,
                $userId
            );

            $notification = DB::transaction(
                function () use (
                    $eventKey,
                    $eventType,
                    $sourceType,
                    $sourceId,
                    $audience,
                    $userId,
                    $baseTemplate,
                    $baseRendered,
                    $context
                ) {
                    return LsankNotification::query()->firstOrCreate(
                        ['event_key' => $eventKey],
                        [
                            'user_id' => $userId,
                            'notification_type' => 'in_app',
                            'event_type' => $eventType,
                            'audience' => $audience,
                            'severity' => $baseTemplate->severity,
                            'priority' => $baseTemplate->priority,
                            'title' =>
                                $baseRendered['title']
                                ?: 'Makluman LSANK',
                            'message' =>
                                $baseRendered['body']
                                ?: '',
                            'related_module' => $sourceType,
                            'related_id' => $sourceId,
                            'action_required' =>
                                $baseTemplate->action_required,
                            'action_label' =>
                                $baseTemplate->action_label,
                            'action_url' =>
                                $context['action_url'] ?? null,
                            'show_as_ribbon' =>
                                $baseTemplate->show_as_ribbon,
                            'ribbon_duration_seconds' =>
                                $baseTemplate->ribbon_duration_seconds,
                            'is_read' => false,
                            'metadata' => $context['metadata'] ?? null,
                            'sent_at' => now(),
                        ]
                    );
                }
            );

            foreach ($templates as $template) {
                if (
                    !$this->channelPolicy->channelEnabled(
                        $userId,
                        $template
                    )
                ) {
                    continue;
                }

                $rendered = $this->templateService->render(
                    $template,
                    $context
                );

                $this->createDelivery(
                    $notification,
                    $recipient,
                    $template,
                    $rendered,
                    $context
                );
            }

            $result[] = [
                'notification_id' =>
                    $notification->notification_id,
                'user_id' => $userId,
                'audience' => $audience,
            ];
        }

        return $result;
    }

    private function createDelivery(
        LsankNotification $notification,
        array $recipient,
        $template,
        array $rendered,
        array $context
    ): void {
        if ($template->channel === 'in_app') {
            $this->createDeliveryRow(
                $notification,
                'in_app',
                (string) $recipient['user_id'],
                [
                    'title' => $rendered['title'],
                    'body' => $rendered['body'],
                ],
                'lsank',
                true
            );

            return;
        }

        if ($template->channel === 'email') {
            $email = trim(
                (string) ($recipient['email'] ?? '')
            );

            if ($email === '') {
                return;
            }

            $delivery = $this->createDeliveryRow(
                $notification,
                'email',
                $email,
                [
                    'subject' =>
                        $rendered['subject']
                        ?: $rendered['title']
                        ?: 'Makluman LSANK',
                    'title' => $rendered['title'],
                    'body' => $rendered['body'],
                    'action_url' =>
                        $context['action_url'] ?? null,
                ],
                config('mail.default', 'smtp')
            );

            if ($delivery->wasRecentlyCreated) {
                SendEmailNotificationJob::dispatch(
                    $delivery->delivery_id
                );
            }

            return;
        }

        if ($template->channel === 'push') {
            $devices = LsankUserDevice::query()
                ->where('user_id', $recipient['user_id'])
                ->where('is_active', true)
                ->get();

            foreach ($devices as $device) {
                $delivery = $this->createDeliveryRow(
                    $notification,
                    'push',
                    $device->fcm_token,
                    [
                        'title' =>
                            $rendered['title']
                            ?: 'Makluman LSANK',
                        'body' => $rendered['body'],
                        'data' => [
                            'notification_id' =>
                                (string) $notification->notification_id,
                            'event_type' =>
                                (string) $notification->event_type,
                            'action_url' =>
                                (string) ($context['action_url'] ?? ''),
                        ],
                    ],
                    'fcm'
                );

                if ($delivery->wasRecentlyCreated) {
                    SendPushNotificationJob::dispatch(
                        $delivery->delivery_id
                    );
                }
            }

            return;
        }

        if ($template->channel === 'whatsapp') {
            $phone = $this->normalizePhone(
                (string) ($recipient['phone'] ?? '')
            );

            if (
                $phone === ''
                || !$template->provider_template_name
            ) {
                return;
            }

            $parameters = collect(
                $template->provider_parameter_keys ?? []
            )
                ->map(
                    fn (string $key) =>
                        (string) data_get(
                            $context,
                            $key,
                            ''
                        )
                )
                ->values()
                ->all();

            $delivery = $this->createDeliveryRow(
                $notification,
                'whatsapp',
                $phone,
                [
                    'template_name' =>
                        $template->provider_template_name,
                    'language' =>
                        $template->language ?: 'ms',
                    'parameters' => $parameters,
                ],
                'meta_whatsapp'
            );

            if ($delivery->wasRecentlyCreated) {
                SendWhatsAppNotificationJob::dispatch(
                    $delivery->delivery_id
                );
            }
        }
    }

    private function createDeliveryRow(
        LsankNotification $notification,
        string $channel,
        ?string $recipient,
        array $payload,
        ?string $provider,
        bool $markSent = false
    ): LsankNotificationDelivery {
        $deliveryKey = sprintf(
            '%d:%s:%s',
            $notification->notification_id,
            $channel,
            sha1($recipient ?: 'none')
        );

        $delivery = LsankNotificationDelivery::query()
            ->firstOrCreate(
                ['delivery_key' => $deliveryKey],
                [
                    'notification_id' =>
                        $notification->notification_id,
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'status' =>
                        $markSent ? 'sent' : 'pending',
                    'provider' => $provider,
                    'payload' => $payload,
                    'queued_at' => now(),
                    'sent_at' =>
                        $markSent ? now() : null,
                    'delivered_at' =>
                        $markSent ? now() : null,
                ]
            );

        return $delivery;
    }

    private function normalizePhone(
        string $phone
    ): string {
        $digits = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (!$digits) {
            return '';
        }

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '60' . substr($digits, 1);
        }

        return $digits;
    }
}