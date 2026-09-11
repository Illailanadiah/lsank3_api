<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $eventCode;

    public string $title;

    public string $message;

    public ?string $referenceNo;

    public ?string $actionUrl;

    public array $data;

    public function __construct(
        string $eventCode,
        string $title,
        string $message,
        ?string $referenceNo = null,
        ?string $actionUrl = null,
        array $data = [],
    ) {
        $this->eventCode = $eventCode;
        $this->title = $title;
        $this->message = $message;
        $this->referenceNo = $referenceNo;
        $this->actionUrl = $actionUrl;
        $this->data = $data;

        $this->onQueue('notifications');
    }

    /**
     * Notification delivery channels.
     *
     * Email is enabled first. The database and Firebase channels
     * can be added later without changing the workflow service.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];

        // Later:
        // return ['mail', 'database', FirebaseChannel::class];
    }

    /**
     * Build the email notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = trim(
            (string) data_get($notifiable, 'name', '')
        );

        $greeting = $recipientName !== ''
            ? "Salam {$recipientName},"
            : 'Salam sejahtera,';

        $mail = (new MailMessage())
            ->subject($this->title)
            ->greeting($greeting)
            ->line($this->message);

        if (
            $this->referenceNo !== null &&
            trim($this->referenceNo) !== ''
        ) {
            $mail->line(
                'No. Rujukan: ' . $this->referenceNo
            );
        }

        if (
            $this->actionUrl !== null &&
            trim($this->actionUrl) !== ''
        ) {
            $mail->action(
                'Lihat Maklumat',
                $this->actionUrl
            );
        }

        return $mail
            ->line(
                'Sila log masuk ke sistem LSANK2U untuk maklumat lanjut.'
            )
            ->salutation(
                "Sekian, terima kasih.\n\n"
                . "Lembaga Sumber Air Negeri Kedah"
            );
    }

    /**
     * Payload for database/in-app notifications.
     *
     * This is already prepared even though the database channel
     * has not yet been enabled.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_code' => $this->eventCode,
            'title' => $this->title,
            'message' => $this->message,
            'reference_no' => $this->referenceNo,
            'action_url' => $this->actionUrl,
            'data' => $this->data,
        ];
    }

    /**
     * Explicit database payload for the next implementation phase.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}