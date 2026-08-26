<?php

namespace App\Jobs\Notifications;

use App\Models\LsankNotificationDelivery;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    private const FCM_SCOPE =
        'https://www.googleapis.com/auth/firebase.messaging';

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
            $token = trim(
                (string) $delivery->recipient
            );

            if ($token === '') {
                $this->markSkipped(
                    $delivery,
                    'FCM token is empty.'
                );

                return;
            }

            $projectId = trim(
                (string) config(
                    'services.firebase.project_id'
                )
            );

            if ($projectId === '') {
                throw new \RuntimeException(
                    'FIREBASE_PROJECT_ID is not configured.'
                );
            }

            $accessToken =
                $this->getFirebaseAccessToken();

            $payload =
                is_array($delivery->payload)
                    ? $delivery->payload
                    : [];

            $title = (string) (
                $payload['title']
                ?? 'Notifikasi LSANK'
            );

            $body = (string) (
                $payload['body']
                ?? ''
            );

            $data =
                $this->buildFcmData(
                    $delivery,
                    $payload
                );

            $fcmPayload = [
                'message' => [
                    'token' => $token,

                    'notification' => [
                        'title' =>
                            $title,

                        'body' =>
                            $body,
                    ],

                    'data' =>
                        $data,
                ],
            ];

            $response =
                Http::withToken(
                    $accessToken
                )
                ->acceptJson()
                ->timeout(30)
                ->post(
                    'https://fcm.googleapis.com/v1/projects/'
                        . rawurlencode(
                            $projectId
                        )
                        . '/messages:send',
                    $fcmPayload
                );

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'FCM HTTP '
                    . $response->status()
                    . ': '
                    . $response->body()
                );
            }

            $providerMessageId =
                data_get(
                    $response->json(),
                    'name'
                );

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

    private function getFirebaseAccessToken(): string
    {
        if (
            !class_exists(
                ServiceAccountCredentials::class
            )
        ) {
            throw new \RuntimeException(
                'google/auth package is not installed. '
                . 'Run: composer require google/auth'
            );
        }

        $credentialsPath = trim(
            (string) config(
                'services.firebase.credentials'
            )
        );

        if ($credentialsPath === '') {
            throw new \RuntimeException(
                'FIREBASE_CREDENTIALS is not configured.'
            );
        }

        if (
            !is_file(
                $credentialsPath
            )
        ) {
            $possiblePath =
                base_path(
                    $credentialsPath
                );

            if (
                is_file(
                    $possiblePath
                )
            ) {
                $credentialsPath =
                    $possiblePath;
            }
        }

        if (
            !is_file(
                $credentialsPath
            )
        ) {
            throw new \RuntimeException(
                'Firebase service account file not found: '
                . $credentialsPath
            );
        }

        $json = json_decode(
            file_get_contents(
                $credentialsPath
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $credentials =
            new ServiceAccountCredentials(
                [
                    self::FCM_SCOPE,
                ],
                $json
            );

        $token =
            $credentials->fetchAuthToken();

        $accessToken =
            $token['access_token']
            ?? null;

        if (!$accessToken) {
            throw new \RuntimeException(
                'Unable to obtain Firebase OAuth access token.'
            );
        }

        return (string)
            $accessToken;
    }

    private function buildFcmData(
        LsankNotificationDelivery $delivery,
        array $payload
    ): array {
        $data = [
            'notification_id' =>
                (string)
                $delivery->notification_id,

            'event_type' =>
                (string) (
                    $payload['event_type']
                    ?? ''
                ),

            'audience' =>
                (string) (
                    $payload['audience']
                    ?? ''
                ),

            'action_url' =>
                (string) (
                    $payload['action_url']
                    ?? ''
                ),

            'severity' =>
                (string) (
                    $payload['severity']
                    ?? 'info'
                ),
        ];

        $extra =
            is_array(
                $payload['data']
                ?? null
            )
                ? $payload['data']
                : [];

        foreach (
            $extra as
            $key => $value
        ) {
            if (
                is_scalar($value)
                || $value === null
            ) {
                $data[
                    (string) $key
                ] = $value === null
                    ? ''
                    : (string) $value;
            }
        }

        return $data;
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
            'FCM notification delivery failed.',
            [
                'delivery_id' =>
                    $delivery->delivery_id,

                'notification_id' =>
                    $delivery->notification_id,

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