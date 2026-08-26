<?php

namespace App\Jobs\Notifications;

use App\Models\LsankNotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $deliveryId
    ) {
    }

    public function backoff(): array
    {
        return [
            60,
            300,
            1800,
        ];
    }

    public function handle(): void
    {
        $delivery =
            $this->beginProcessing();

        if (!$delivery) {
            return;
        }

        try {
            $recipient = trim(
                (string)
                $delivery->recipient
            );

            if ($recipient === '') {
                $this->markSkipped(
                    $delivery,
                    'WhatsApp recipient is empty.'
                );

                return;
            }

            $accessToken = trim(
                (string) config(
                    'services.whatsapp.access_token'
                )
            );

            $phoneNumberId = trim(
                (string) config(
                    'services.whatsapp.phone_number_id'
                )
            );

            $graphVersion = trim(
                (string) config(
                    'services.whatsapp.graph_version'
                )
            );

            if ($accessToken === '') {
                throw new \RuntimeException(
                    'WHATSAPP_ACCESS_TOKEN is not configured.'
                );
            }

            if ($phoneNumberId === '') {
                throw new \RuntimeException(
                    'WHATSAPP_PHONE_NUMBER_ID is not configured.'
                );
            }

            if ($graphVersion === '') {
                throw new \RuntimeException(
                    'WHATSAPP_GRAPH_VERSION is not configured.'
                );
            }

            $payload =
                is_array($delivery->payload)
                    ? $delivery->payload
                    : [];

            $templateName = trim(
                (string) (
                    $payload[
                        'provider_template_name'
                    ]
                    ?? ''
                )
            );

            if ($templateName === '') {
                throw new \RuntimeException(
                    'WhatsApp approved template name is missing.'
                );
            }

            $language = trim(
                (string) (
                    $payload['language']
                    ?? 'ms'
                )
            );

            $providerParameters =
                is_array(
                    $payload[
                        'provider_parameters'
                    ]
                    ?? null
                )
                    ? $payload[
                        'provider_parameters'
                    ]
                    : [];

            /*
             * Convert associative values to ordered
             * Meta template parameters.
             *
             * NotificationTemplateService should already
             * place parameters in the correct order.
             */
            $parameters = collect(
                $providerParameters
            )
                ->values()
                ->map(
                    fn($value) => [
                        'type' => 'text',

                        'text' =>
                            $value === null
                                ? ''
                                : (string) $value,
                    ]
                )
                ->all();

            $template = [
                'name' =>
                    $templateName,

                'language' => [
                    'code' =>
                        $language,
                ],
            ];

            if (!empty($parameters)) {
                $template['components'] = [
                    [
                        'type' =>
                            'body',

                        'parameters' =>
                            $parameters,
                    ],
                ];
            }

            $requestPayload = [
                'messaging_product' =>
                    'whatsapp',

                'recipient_type' =>
                    'individual',

                'to' =>
                    $recipient,

                'type' =>
                    'template',

                'template' =>
                    $template,
            ];

            $endpoint =
                'https://graph.facebook.com/'
                . rawurlencode(
                    $graphVersion
                )
                . '/'
                . rawurlencode(
                    $phoneNumberId
                )
                . '/messages';

            $response =
                Http::withToken(
                    $accessToken
                )
                ->acceptJson()
                ->timeout(30)
                ->post(
                    $endpoint,
                    $requestPayload
                );

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'WhatsApp API HTTP '
                    . $response->status()
                    . ': '
                    . $response->body()
                );
            }

            $providerMessageId =
                data_get(
                    $response->json(),
                    'messages.0.id'
                );

            /*
             * "sent" here means Meta accepted the
             * outgoing message.
             *
             * delivered/read status should later be
             * updated by WhatsApp webhook.
             */
            $delivery->status =
                'sent';

            $delivery->provider_message_id =
                $providerMessageId;

            $delivery->sent_at =
                now();

            $delivery->failed_at =
                null;

            $delivery->next_attempt_at =
                null;

            $delivery->last_error =
                null;

            $delivery->save();
        } catch (\Throwable $exception) {
            $this->recordFailure(
                $delivery,
                $exception
            );

            throw $exception;
        }
    }

    private function beginProcessing(): ?LsankNotificationDelivery
    {
        return DB::transaction(
            function () {
                $delivery =
                    LsankNotificationDelivery::query()
                    ->where(
                        'delivery_id',
                        $this->deliveryId
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$delivery) {
                    return null;
                }

                if (
                    in_array(
                        $delivery->status,
                        [
                            'sent',
                            'delivered',
                            'skipped',
                        ],
                        true
                    )
                ) {
                    return null;
                }

                /*
                 * Prevent duplicate concurrent send.
                 * Recover stale processing records after
                 * 10 minutes.
                 */
                if (
                    $delivery->status ===
                    'processing'
                    && $delivery->processing_at
                    && $delivery
                        ->processing_at
                        ->gt(
                            now()
                                ->subMinutes(
                                    10
                                )
                        )
                ) {
                    return null;
                }

                $delivery->status =
                    'processing';

                $delivery->processing_at =
                    now();

                $delivery->failed_at =
                    null;

                $delivery->next_attempt_at =
                    null;

                $delivery->attempt_count =
                    (int)
                    $delivery->attempt_count
                    + 1;

                $delivery->save();

                return $delivery->fresh();
            }
        );
    }

    private function recordFailure(
        LsankNotificationDelivery $delivery,
        \Throwable $exception
    ): void {
        $attempt =
            (int)
            $delivery->attempt_count;

        $delay =
            match ($attempt) {
                1 => 60,
                2 => 300,
                default => null,
            };

        $delivery->status =
            'failed';

        $delivery->last_error =
            mb_substr(
                $exception
                    ->getMessage(),
                0,
                65000
            );

        $delivery->failed_at =
            now();

        $delivery->next_attempt_at =
            $delay !== null
                ? now()
                    ->addSeconds(
                        $delay
                    )
                : null;

        $delivery->save();

        Log::error(
            'WhatsApp notification delivery failed.',
            [
                'delivery_id' =>
                    $delivery->delivery_id,

                'notification_id' =>
                    $delivery->notification_id,

                'recipient' =>
                    $delivery->recipient,

                'attempt' =>
                    $attempt,

                'error' =>
                    $exception->getMessage(),
            ]
        );
    }

    private function markSkipped(
        LsankNotificationDelivery $delivery,
        string $reason
    ): void {
        $delivery->status =
            'skipped';

        $delivery->last_error =
            $reason;

        $delivery->next_attempt_at =
            null;

        $delivery->save();
    }

    public function failed(
        ?\Throwable $exception
    ): void {
        $delivery =
            LsankNotificationDelivery::query()
            ->find(
                $this->deliveryId
            );

        if (!$delivery) {
            return;
        }

        if (
            in_array(
                $delivery->status,
                [
                    'sent',
                    'delivered',
                    'skipped',
                ],
                true
            )
        ) {
            return;
        }

        $delivery->status =
            'failed';

        $delivery->failed_at =
            now();

        $delivery->next_attempt_at =
            null;

        if ($exception) {
            $delivery->last_error =
                mb_substr(
                    $exception
                        ->getMessage(),
                    0,
                    65000
                );
        }

        $delivery->save();
    }
}