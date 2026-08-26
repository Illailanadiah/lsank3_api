<?php

namespace App\Services\Notifications;

use App\Models\LsankNotificationTemplate;
use Illuminate\Support\Collection;

class NotificationTemplateService
{
    public function templatesFor(
        string $eventType,
        string $audience,
        string $language = 'ms'
    ): Collection {
        $exact = LsankNotificationTemplate::query()
            ->where('event_type', $eventType)
            ->where('audience', $audience)
            ->where('language', $language)
            ->where('is_enabled', true)
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return LsankNotificationTemplate::query()
            ->where('event_type', $eventType)
            ->where('audience', 'all')
            ->where('language', $language)
            ->where('is_enabled', true)
            ->get();
    }

    public function render(
        LsankNotificationTemplate $template,
        array $context
    ): array {
        return [
            'subject' => $this->renderText(
                $template->subject_template,
                $context
            ),
            'title' => $this->renderText(
                $template->title_template,
                $context
            ),
            'body' => $this->renderText(
                $template->body_template,
                $context
            ),
        ];
    }

    private function renderText(
        ?string $template,
        array $context
    ): ?string {
        if ($template === null) {
            return null;
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            function (array $matches) use ($context) {
                $value = data_get(
                    $context,
                    $matches[1],
                    ''
                );

                if (is_array($value) || is_object($value)) {
                    return '';
                }

                return (string) $value;
            },
            $template
        );
    }
}