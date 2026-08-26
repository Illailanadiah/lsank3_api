<?php

namespace App\Jobs\Notifications;

use App\Models\LsankNotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
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
                (string) $delivery->recipient
            );

            if (
                $recipient === ''
                || !filter_var(
                    $recipient,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $this->markSkipped(
                    $delivery,
                    'Invalid email recipient.'
                );

                return;
            }

            $payload =
                is_array($delivery->payload)
                    ? $delivery->payload
                    : [];

            $title = trim(
                (string) (
                    $payload['title']
                    ?? 'Notifikasi LSANK'
                )
            );

            $subject = trim(
                (string) (
                    $payload['subject']
                    ?? $title
                )
            );

            $body = trim(
                (string) (
                    $payload['body']
                    ?? ''
                )
            );

            $actionUrl =
                $this->resolveActionUrl(
                    $payload['action_url']
                    ?? null
                );

            $html =
                $this->buildHtml(
                    $title,
                    $body,
                    $actionUrl,
                    $payload[
                        'action_label'
                    ]
                    ?? null
                );

            Mail::html(
                $html,
                function (
                    Message $mail
                ) use (
                    $recipient,
                    $subject
                ): void {
                    $mail
                        ->to($recipient)
                        ->subject(
                            $subject
                        );
                }
            );

            $delivery->status =
                'sent';

            $delivery->sent_at =
                now();

            $delivery->delivered_at =
                null;

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
                 * Avoid two workers sending the same
                 * notification concurrently.
                 *
                 * A processing record older than
                 * 10 minutes is considered stale.
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
            (int) $delivery
                ->attempt_count;

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
                $exception->getMessage(),
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
            'Notification email delivery failed.',
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

    private function resolveActionUrl(
        ?string $actionUrl
    ): ?string {
        if (
            !$actionUrl
            || trim($actionUrl) === ''
        ) {
            return null;
        }

        $actionUrl =
            trim($actionUrl);

        if (
            str_starts_with(
                $actionUrl,
                'http://'
            )
            || str_starts_with(
                $actionUrl,
                'https://'
            )
        ) {
            return $actionUrl;
        }

        $frontendUrl =
            config(
                'app.frontend_url'
            )
            ?: config(
                'app.url'
            );

        return rtrim(
            (string) $frontendUrl,
            '/'
        )
            . '/'
            . ltrim(
                $actionUrl,
                '/'
            );
    }

    private function buildHtml(
        string $title,
        string $body,
        ?string $actionUrl,
        ?string $actionLabel
    ): string {
        $safeTitle =
            htmlspecialchars(
                $title,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            );

        $safeBody =
            nl2br(
                htmlspecialchars(
                    $body,
                    ENT_QUOTES |
                    ENT_SUBSTITUTE,
                    'UTF-8'
                )
            );

        $button = '';

        if ($actionUrl) {
            $safeUrl =
                htmlspecialchars(
                    $actionUrl,
                    ENT_QUOTES |
                    ENT_SUBSTITUTE,
                    'UTF-8'
                );

            $safeLabel =
                htmlspecialchars(
                    $actionLabel
                    ?: 'Lihat Maklumat',
                    ENT_QUOTES |
                    ENT_SUBSTITUTE,
                    'UTF-8'
                );

            $button = <<<HTML
<p style="margin-top:24px;">
    <a
        href="{$safeUrl}"
        style="
            display:inline-block;
            padding:12px 20px;
            background:#0d6efd;
            color:#ffffff;
            text-decoration:none;
            border-radius:6px;
        "
    >
        {$safeLabel}
    </a>
</p>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>{$safeTitle}</title>
</head>
<body
    style="
        font-family:Arial,sans-serif;
        line-height:1.6;
        color:#222222;
    "
>
    <div
        style="
            max-width:640px;
            margin:0 auto;
            padding:24px;
        "
    >
        <h2>{$safeTitle}</h2>

        <div>
            {$safeBody}
        </div>

        {$button}

        <hr style="margin-top:32px;">

        <p
            style="
                font-size:12px;
                color:#777777;
            "
        >
            Notifikasi automatik Sistem LSANK.
        </p>
    </div>
</body>
</html>
HTML;
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