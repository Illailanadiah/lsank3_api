<?php

namespace App\Services\Notifications\Channels;

use App\Models\LsankNotification;
use Illuminate\Support\Collection;

interface NotificationChannel
{
    /**
     * Nama channel.
     *
     * Contoh:
     * - in_app
     * - email
     * - push
     * - whatsapp
     */
    public function name(): string;

    /**
     * Create / queue notification delivery.
     *
     * Recipient standard:
     *
     * [
     *     'user_id' => 1,
     *     'email' => 'user@example.com',
     *     'phone' => '60123456789',
     * ]
     *
     * Message standard:
     *
     * [
     *     'title' => '...',
     *     'subject' => '...',
     *     'body' => '...',
     *     'action_url' => '...',
     *     'language' => 'ms',
     * ]
     */
    public function dispatch(
        LsankNotification $notification,
        array $recipient,
        array $message,
        array $context = []
    ): Collection;
}