<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankNotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceController extends Controller
{
    /**
     * Return notification preferences for
     * the currently authenticated user.
     *
     * GET /api/notification-preferences
     */
    public function show(Request $request)
    {
        $userId = $this->userId($request);

        /*
         * Automatically create default preferences
         * the first time the user opens the settings.
         */
        $preference =
            LsankNotificationPreference::query()
                ->firstOrCreate(
                    [
                        'user_id' => $userId,
                    ],
                    [
                        /*
                         * In-app should remain enabled
                         * by default because Bell / Ribbon
                         * are part of the core system.
                         */
                        'in_app_enabled' => true,

                        /*
                         * These can later be disabled
                         * individually by the user.
                         */
                        'push_enabled' => true,
                        'email_enabled' => true,
                        'whatsapp_enabled' => true,
                    ]
                );

        return response()->json([
            'success' => true,

            'preferences' =>
                $this->formatPreference(
                    $preference
                ),

            /*
             * Alias for Flutter/API compatibility.
             */
            'data' =>
                $this->formatPreference(
                    $preference
                ),
        ]);
    }

    /**
     * Update notification preferences.
     *
     * PATCH /api/notification-preferences
     *
     * Example:
     *
     * {
     *   "push_enabled": true,
     *   "email_enabled": false
     * }
     */
    public function update(Request $request)
    {
        $validated =
            $request->validate([
                'in_app_enabled' => [
                    'sometimes',
                    'boolean',
                ],

                'push_enabled' => [
                    'sometimes',
                    'boolean',
                ],

                'email_enabled' => [
                    'sometimes',
                    'boolean',
                ],

                'whatsapp_enabled' => [
                    'sometimes',
                    'boolean',
                ],
            ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Tiada tetapan notifikasi '
                    . 'diterima untuk dikemas kini.',
            ], 422);
        }

        $userId = $this->userId($request);

        $preference = DB::transaction(
            function () use (
                $userId,
                $validated
            ) {
                $preference =
                    LsankNotificationPreference::query()
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                 * User may not yet have a preference row.
                 */
                if (!$preference) {
                    $preference =
                        LsankNotificationPreference::create([
                            'user_id' =>
                                $userId,

                            'in_app_enabled' =>
                                true,

                            'push_enabled' =>
                                true,

                            'email_enabled' =>
                                true,

                            'whatsapp_enabled' =>
                                true,
                        ]);
                }

                /*
                 * Update only values supplied by frontend.
                 */
                foreach (
                    [
                        'in_app_enabled',
                        'push_enabled',
                        'email_enabled',
                        'whatsapp_enabled',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $validated
                        )
                    ) {
                        $preference->{$field} =
                            (bool) $validated[$field];
                    }
                }

                $preference->save();

                return $preference->fresh();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Tetapan notifikasi berjaya dikemas kini.',

            'preferences' =>
                $this->formatPreference(
                    $preference
                ),

            'data' =>
                $this->formatPreference(
                    $preference
                ),
        ]);
    }

    /**
     * Optional endpoint:
     * Reset preferences to LSANK defaults.
     *
     * POST /api/notification-preferences/reset
     */
    public function reset(Request $request)
    {
        $userId = $this->userId($request);

        $preference =
            LsankNotificationPreference::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $userId,
                ],
                [
                    'in_app_enabled' =>
                        true,

                    'push_enabled' =>
                        true,

                    'email_enabled' =>
                        true,

                    'whatsapp_enabled' =>
                        true,
                ]
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Tetapan notifikasi telah '
                . 'dikembalikan kepada tetapan asal.',

            'preferences' =>
                $this->formatPreference(
                    $preference
                ),

            'data' =>
                $this->formatPreference(
                    $preference
                ),
        ]);
    }

    /**
     * Resolve authenticated LSANK user ID.
     *
     * The LSANK auth model uses user_id as
     * its primary key, therefore getKey()
     * is preferred.
     */
    private function userId(
        Request $request
    ): int {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Unauthenticated.'
        );

        $userId = (int) (
            $user->getKey()
            ?? $user->user_id
            ?? $user->id
            ?? 0
        );

        abort_unless(
            $userId > 0,
            401,
            'Authenticated user ID is invalid.'
        );

        return $userId;
    }

    /**
     * Standard JSON representation.
     */
    private function formatPreference(
        LsankNotificationPreference $preference
    ): array {
        return [
            'preference_id' =>
                (int) $preference
                    ->preference_id,

            'user_id' =>
                (int) $preference
                    ->user_id,

            'in_app_enabled' =>
                (bool) $preference
                    ->in_app_enabled,

            'push_enabled' =>
                (bool) $preference
                    ->push_enabled,

            'email_enabled' =>
                (bool) $preference
                    ->email_enabled,

            'whatsapp_enabled' =>
                (bool) $preference
                    ->whatsapp_enabled,

            'created_at' =>
                optional(
                    $preference->created_at
                )?->toIso8601String(),

            'updated_at' =>
                optional(
                    $preference->updated_at
                )?->toIso8601String(),
        ];
    }
}
