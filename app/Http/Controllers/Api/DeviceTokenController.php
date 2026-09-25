<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\FcmPushService;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => [
                'required',
                Rule::in(['android', 'ios', 'web']),
            ],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        $userId = $request->user()->getKey();
        $tokenHash = hash('sha256', $validated['token']);

        /*
         * An FCM token belongs to only one currently authenticated user.
         * This prevents notifications being sent to the previous user
         * after another user signs in on the same device.
         */
        $deviceToken = LsankDeviceToken::updateOrCreate(
            ['token_hash' => $tokenHash],
            [
                'user_id' => $userId,
                'token' => $validated['token'],
                'device_id' => $validated['device_id'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Token notifikasi berjaya didaftarkan.',
            'data' => [
                'device_token_id' => $deviceToken->device_token_id,
                'platform' => $deviceToken->platform,
                'is_active' => $deviceToken->is_active,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        LsankDeviceToken::query()
            ->where('user_id', $request->user()->getKey())
            ->where('token_hash', hash('sha256', $validated['token']))
            ->update([
                'is_active' => false,
                'last_used_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Token notifikasi berjaya dinyahaktifkan.',
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        LsankDeviceToken::query()
            ->where('user_id', $request->user()->getKey())
            ->update([
                'is_active' => false,
                'last_used_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Semua token notifikasi berjaya dinyahaktifkan.',
        ]);
    }
    

    public function test(
    Request $request,
    FcmPushService $pushService
): JsonResponse {
    $user = $request->user();

    $result = $pushService->sendToUser(
        $user,
        'Ujian Notifikasi LSANK',
        'Notifikasi Firebase berjaya dihantar ke peranti anda.',
        [
            'event_code' => 'push_test',
            'screen' => '/notifications',
            'user_id' => $user->getKey(),
        ]
    );

    return response()->json([
        'success' => $result['sent'] > 0,
        'message' => $result['sent'] > 0
            ? 'Notifikasi ujian berjaya dihantar.'
            : 'Tiada notifikasi berjaya dihantar. Pastikan token FCM telah didaftarkan.',
        'data' => $result,
    ], $result['sent'] > 0 ? 200 : 422);
}
}