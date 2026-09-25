<?php

namespace App\Notifications;

use App\Notifications\Channels\FirebaseChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $eventCode,
        public string $title,
        public string $message,
        public string $actionUrl,
        public array $payload = [],
        public array $channels = ['mail', 'database', FirebaseChannel::class],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Salam sejahtera,')
            ->line($this->message)
            ->action('Lihat Maklumat', $this->actionUrl)
            ->line('Ini ialah notifikasi automatik daripada sistem LSANK.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_code' => $this->eventCode,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'payload' => $this->payload,
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'data' => array_merge($this->payload, [
                'event_code' => $this->eventCode,
                'action_url' => $this->actionUrl,
            ]),
        ];
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'template' => 'lsank_workflow_update',
            'language' => 'ms',
            'parameters' => [
                $notifiable->name,
                $this->title,
                $this->message,
            ],
        ];
    }
}