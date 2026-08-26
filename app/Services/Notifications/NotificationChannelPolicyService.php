<?php

namespace App\Services\Notifications;

use App\Models\LsankNotificationPreference;
use App\Models\LsankNotificationTemplate;

class NotificationChannelPolicyService
{
    public function channelEnabled(
        int $userId,
        LsankNotificationTemplate $template
    ): bool {
        if ($template->mandatory) {
            return true;
        }

        $preference = LsankNotificationPreference::query()
            ->where('user_id', $userId)
            ->first();

        if (!$preference) {
            return match ($template->channel) {
                'in_app' => true,
                'push' => true,
                'email' => true,
                'whatsapp' => false,
                default => false,
            };
        }

        return match ($template->channel) {
            'in_app' => (bool) $preference->in_app_enabled,
            'push' => (bool) $preference->push_enabled,
            'email' => (bool) $preference->email_enabled,
            'whatsapp' => (bool) $preference->whatsapp_enabled,
            default => false,
        };
    }
}