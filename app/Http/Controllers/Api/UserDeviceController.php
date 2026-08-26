<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankUserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDeviceController extends Controller
{
    /**
     * List active devices belonging to
     * the currently authenticated user.
     *
     * GET /api/devices
     */
    public function index(Request $request)
    {
        $userId = $this->userId($request);

        $devices =
            LsankUserDevice::query()
            ->where(
                'user_id',
                $userId
            )
            ->orderByDesc(
                'is_active'
            )
            ->orderByDesc(
                'last_seen_at'
            )
            ->orderByDesc(
                'device_id'
            )
            ->get()
            ->map(
                fn (
                    LsankUserDevice $device
                ) =>
                $this->formatDevice(
                    $device
                )
            )
            ->values();

        return response()->json([
            'success' => true,

            'total' =>
                $devices->count(),

            'active_count' =>
                $devices
                    ->where(
                        'is_active',
                        true
                    )
                    ->count(),

            'devices' =>
                $devices,

            'data' =>
                $devices,
        ]);
    }

    /**
     * Register or refresh one FCM token.
     *
     * POST /api/devices/register
     *
     * Example:
     *
     * {
     *   "fcm_token": "...",
     *   "platform": "web",
     *   "device_name": "Chrome - Windows",
     *   "app_version": "1.0.0"
     * }
     */
    public function register(
        Request $request
    ) {
        $validated =
            $request->validate([
                'fcm_token' => [
                    'required',
                    'string',
                    'min:10',
                    'max:4096',
                ],

                'platform' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'device_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'app_version' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ]);

        $userId =
            $this->userId($request);

        $token = trim(
            $validated['fcm_token']
        );

        $platform =
            $this->normalizePlatform(
                $validated['platform']
            );

        $result = DB::transaction(
            function () use (
                $userId,
                $token,
                $platform,
                $validated
            ) {
                /*
                 * FCM token should belong to only one
                 * active user at a time.
                 *
                 * This is important when:
                 *
                 * User A logs out,
                 * User B logs in on the same browser,
                 * Firebase returns the same token.
                 */
                $existingToken =
                    LsankUserDevice::query()
                    ->where(
                        'fcm_token',
                        $token
                    )
                    ->lockForUpdate()
                    ->first();

                $created = false;

                if ($existingToken) {
                    /*
                     * Reassign token to the current
                     * authenticated LSANK account.
                     */
                    $existingToken->forceFill([
                        'user_id' =>
                            $userId,

                        'platform' =>
                            $platform,

                        'device_name' =>
                            $validated[
                                'device_name'
                            ]
                            ?? $existingToken
                                ->device_name,

                        'app_version' =>
                            $validated[
                                'app_version'
                            ]
                            ?? $existingToken
                                ->app_version,

                        'is_active' =>
                            true,

                        'last_seen_at' =>
                            now(),
                    ])->save();

                    $device =
                        $existingToken;
                } else {
                    $device =
                        LsankUserDevice::create([
                            'user_id' =>
                                $userId,

                            'fcm_token' =>
                                $token,

                            'platform' =>
                                $platform,

                            'device_name' =>
                                $validated[
                                    'device_name'
                                ]
                                ?? null,

                            'app_version' =>
                                $validated[
                                    'app_version'
                                ]
                                ?? null,

                            'is_active' =>
                                true,

                            'last_seen_at' =>
                                now(),
                        ]);

                    $created = true;
                }

                /*
                 * Defensive cleanup:
                 *
                 * if duplicate rows somehow existed
                 * before this implementation, disable
                 * any other row with the same token.
                 */
                LsankUserDevice::query()
                    ->where(
                        'fcm_token',
                        $token
                    )
                    ->where(
                        'device_id',
                        '!=',
                        $device->device_id
                    )
                    ->update([
                        'is_active' =>
                            false,

                        'updated_at' =>
                            now(),
                    ]);

                return [
                    'device' =>
                        $device->fresh(),

                    'created' =>
                        $created,
                ];
            }
        );

        /** @var LsankUserDevice $device */
        $device =
            $result['device'];

        return response()->json([
            'success' => true,

            'message' =>
                $result['created']
                ? 'Peranti berjaya didaftarkan untuk notifikasi push.'
                : 'Token notifikasi peranti berjaya dikemas kini.',

            'created' =>
                $result['created'],

            'device' =>
                $this->formatDevice(
                    $device
                ),

            'data' =>
                $this->formatDevice(
                    $device
                ),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Mark a device/token as active and update
     * last_seen_at.
     *
     * Optional but useful when Flutter starts.
     *
     * POST /api/devices/touch
     */
    public function touch(
        Request $request
    ) {
        $validated =
            $request->validate([
                'fcm_token' => [
                    'required',
                    'string',
                    'max:4096',
                ],

                'app_version' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ]);

        $userId =
            $this->userId($request);

        $device =
            LsankUserDevice::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'fcm_token',
                trim(
                    $validated['fcm_token']
                )
            )
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Peranti tidak dijumpai. '
                    . 'Sila daftar semula token FCM.',
            ], 404);
        }

        $device->forceFill([
            'is_active' =>
                true,

            'last_seen_at' =>
                now(),

            'app_version' =>
                $validated['app_version']
                ?? $device->app_version,
        ])->save();

        return response()->json([
            'success' => true,

            'device' =>
                $this->formatDevice(
                    $device->fresh()
                ),
        ]);
    }

    /**
     * Unregister one FCM token.
     *
     * POST /api/devices/unregister
     *
     * {
     *   "fcm_token": "..."
     * }
     *
     * We keep the record for history but
     * set is_active = false.
     */
    public function unregister(
        Request $request
    ) {
        $validated =
            $request->validate([
                'fcm_token' => [
                    'nullable',
                    'string',
                    'max:4096',
                    'required_without:device_id',
                ],

                'device_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'required_without:fcm_token',
                ],
            ]);

        $userId =
            $this->userId($request);

        $query =
            LsankUserDevice::query()
            ->where(
                'user_id',
                $userId
            );

        if (
            !empty(
                $validated['device_id']
            )
        ) {
            $query->where(
                'device_id',
                (int) $validated[
                    'device_id'
                ]
            );
        } else {
            $query->where(
                'fcm_token',
                trim(
                    (string)
                    $validated[
                        'fcm_token'
                    ]
                )
            );
        }

        $device =
            $query->first();

        /*
         * Unregister should be idempotent.
         *
         * Flutter logout should not fail just
         * because the token was already removed.
         */
        if (!$device) {
            return response()->json([
                'success' => true,

                'message' =>
                    'Peranti telah dinyahdaftar '
                    . 'atau tidak lagi aktif.',
            ]);
        }

        $device->forceFill([
            'is_active' =>
                false,

            'last_seen_at' =>
                now(),
        ])->save();

        return response()->json([
            'success' => true,

            'message' =>
                'Peranti berjaya dinyahdaftar '
                . 'daripada notifikasi push.',

            'device' =>
                $this->formatDevice(
                    $device->fresh()
                ),
        ]);
    }

    /**
     * Disable all push devices belonging
     * to the current user.
     *
     * Useful during:
     * - logout all sessions
     * - account security reset
     *
     * POST /api/devices/unregister-all
     */
    public function unregisterAll(
        Request $request
    ) {
        $userId =
            $this->userId($request);

        $count =
            LsankUserDevice::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'is_active',
                true
            )
            ->update([
                'is_active' =>
                    false,

                'last_seen_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Semua peranti telah dinyahdaftar '
                . 'daripada notifikasi push.',

            'deactivated_count' =>
                (int) $count,
        ]);
    }

    /**
     * Permanently delete one inactive device.
     *
     * This endpoint is optional.
     *
     * DELETE /api/devices/{device}
     */
    public function destroy(
        Request $request,
        $device
    ) {
        $userId =
            $this->userId($request);

        $record =
            LsankUserDevice::query()
            ->where(
                'device_id',
                (int) $device
            )
            ->where(
                'user_id',
                $userId
            )
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Peranti tidak dijumpai.',
            ], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Rekod peranti berjaya dipadam.',
        ]);
    }

    /**
     * Normalize device platform.
     */
    private function normalizePlatform(
        string $platform
    ): string {
        $platform = strtolower(
            trim($platform)
        );

        return match ($platform) {
            'android' =>
                'android',

            'ios',
            'iphone',
            'ipad' =>
                'ios',

            'web',
            'browser',
            'chrome',
            'edge',
            'firefox',
            'safari' =>
                'web',

            'windows' =>
                'windows',

            'mac',
            'macos' =>
                'macos',

            'linux' =>
                'linux',

            default =>
                'unknown',
        };
    }

    /**
     * Resolve authenticated LSANK user ID.
     */
    private function userId(
        Request $request
    ): int {
        $user =
            $request->user();

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
     * Return device without exposing the
     * complete FCM token.
     */
    private function formatDevice(
        LsankUserDevice $device
    ): array {
        $token = trim(
            (string)
            $device->fcm_token
        );

        return [
            'device_id' =>
                (int)
                $device->device_id,

            'user_id' =>
                (int)
                $device->user_id,

            /*
             * Do not send full FCM tokens back
             * in normal API responses.
             */
            'token_preview' =>
                $this->tokenPreview(
                    $token
                ),

            'platform' =>
                $device->platform,

            'device_name' =>
                $device->device_name,

            'app_version' =>
                $device->app_version,

            'is_active' =>
                (bool)
                $device->is_active,

            'last_seen_at' =>
                optional(
                    $device->last_seen_at
                )?->toIso8601String(),

            'created_at' =>
                optional(
                    $device->created_at
                )?->toIso8601String(),

            'updated_at' =>
                optional(
                    $device->updated_at
                )?->toIso8601String(),
        ];
    }

    /**
     * Safely display only part of the token.
     */
    private function tokenPreview(
        string $token
    ): ?string {
        if ($token === '') {
            return null;
        }

        $length =
            strlen($token);

        if ($length <= 16) {
            return substr(
                $token,
                0,
                4
            )
                . '...';
        }

        return substr(
            $token,
            0,
            8
        )
            . '...'
            . substr(
                $token,
                -6
            );
    }
}