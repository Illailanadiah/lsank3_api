<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $limit = max(1, min($request->integer('limit', 30), 100));

        $notifications = $this->baseQuery($userId)
            ->orderBy('is_read')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LsankNotification $notification): array =>
                $this->formatNotification($notification))
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadCount($userId),
            'action_required_count' => $this->actionRequiredCount($userId),
            'notifications' => $notifications,
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $notifications = $this->baseQuery($userId)
            ->where('is_read', false)
            ->whereNull('read_at')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (LsankNotification $notification): array =>
                $this->formatNotification($notification))
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    public function actionRequired(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $notifications = $this->baseQuery($userId)
            ->where('action_required', true)
            ->whereNull('action_completed_at')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (LsankNotification $notification): array =>
                $this->formatNotification($notification))
            ->values();

        return response()->json([
            'success' => true,
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    public function ribbon(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $notification = $this->baseQuery($userId)
            ->where('show_as_ribbon', true)
            ->where('action_required', true)
            ->where('is_read', false)
            ->whereNull('read_at')
            ->whereNull('action_completed_at')
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'notification' => $notification
                ? $this->formatNotification($notification)
                : null,
            'unread_count' => $this->unreadCount($userId),
            'action_required_count' => $this->actionRequiredCount($userId),
        ]);
    }

    public function markShown(
        Request $request,
        LsankNotification $notification
    ): JsonResponse {
        $this->authorizeNotification($request, $notification);

        $now = now();

        $notification->forceFill([
            'first_shown_at' => $notification->first_shown_at ?? $now,
            'last_shown_at' => $now,
            'shown_count' => ((int) $notification->shown_count) + 1,
        ])->save();

        return response()->json([
            'success' => true,
            'notification' => $this->formatNotification($notification->fresh()),
        ]);
    }

    public function markRead(
        Request $request,
        LsankNotification $notification
    ): JsonResponse {
        $this->authorizeNotification($request, $notification);

        $notification->forceFill([
            'is_read' => true,
            'read_at' => $notification->read_at ?? now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandakan sebagai dibaca.',
            'notification' => $this->formatNotification($notification->fresh()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $now = now();

        $this->baseQuery($userId)
            ->where(function (Builder $query): void {
                $query
                    ->where('is_read', false)
                    ->orWhereNull('read_at');
            })
            ->update([
                'is_read' => true,
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi telah ditandakan sebagai dibaca.',
            'unread_count' => 0,
        ]);
    }

    public function dismiss(
        Request $request,
        LsankNotification $notification
    ): JsonResponse {
        $this->authorizeNotification($request, $notification);

        $notification->forceFill([
            'dismissed_at' => $notification->dismissed_at ?? now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ribbon telah ditutup.',
        ]);
    }

    public function complete(
        Request $request,
        LsankNotification $notification
    ): JsonResponse {
        $this->authorizeNotification($request, $notification);

        abort_unless(
            (bool) $notification->action_required,
            422,
            'Notifikasi ini tidak memerlukan tindakan.'
        );

        $now = now();

        $notification->forceFill([
            'action_completed_at' => $notification->action_completed_at ?? $now,
            'is_read' => true,
            'read_at' => $notification->read_at ?? $now,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Tindakan notifikasi telah diselesaikan.',
            'notification' => $this->formatNotification($notification->fresh()),
        ]);
    }

    private function baseQuery(int $userId): Builder
    {
        return LsankNotification::query()
            ->where('user_id', $userId)
            ->where('notification_type', 'in_app')
            ->whereNull('dismissed_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    private function userId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated.');

        $userId = (int) $user->getKey();

        abort_unless($userId > 0, 401, 'Authenticated user ID is invalid.');

        return $userId;
    }

    private function authorizeNotification(
        Request $request,
        LsankNotification $notification
    ): void {
        abort_unless(
            (int) $notification->user_id === $this->userId($request)
                && $notification->notification_type === 'in_app',
            403,
            'Anda tidak dibenarkan mengakses notifikasi ini.'
        );
    }

    private function unreadCount(int $userId): int
    {
        return (int) $this->baseQuery($userId)
            ->where('is_read', false)
            ->whereNull('read_at')
            ->count();
    }

    private function actionRequiredCount(int $userId): int
    {
        return (int) $this->baseQuery($userId)
            ->where('action_required', true)
            ->whereNull('action_completed_at')
            ->count();
    }

    private function formatNotification(
        LsankNotification $notification
    ): array {
        return [
            'notification_id' => $notification->notification_id,
            'user_id' => $notification->user_id,
            'notification_type' => $notification->notification_type,
            'event_type' => $notification->event_type,
            'audience' => $notification->audience,
            'severity' => $notification->severity,
            'priority' => (int) $notification->priority,
            'title' => $notification->title,
            'message' => $notification->message,
            'related_module' => $notification->related_module,
            'related_id' => $notification->related_id,
            'action_required' => (bool) $notification->action_required,
            'action_label' => $notification->action_label,
            'action_url' => $notification->action_url,
            'show_as_ribbon' => (bool) $notification->show_as_ribbon,
            'ribbon_duration_seconds' =>
                (int) $notification->ribbon_duration_seconds,
            'shown_count' => (int) $notification->shown_count,
            'is_read' => (bool) $notification->is_read,
            'metadata' => $notification->metadata ?? [],
            'first_shown_at' =>
                $notification->first_shown_at?->toIso8601String(),
            'last_shown_at' =>
                $notification->last_shown_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
            'dismissed_at' => $notification->dismissed_at?->toIso8601String(),
            'action_completed_at' =>
                $notification->action_completed_at?->toIso8601String(),
            'expires_at' => $notification->expires_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'updated_at' => $notification->updated_at?->toIso8601String(),
        ];
    }
}
