<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    private function verifyMobileRecaptcha(
        Request $request,
        string $expectedAction
    ): ?\Illuminate\Http\JsonResponse {
        $request->validate([
            'recaptcha_token' => 'required|string',
            'recaptcha_platform' => 'required|in:mobile',
        ]);

        $projectId = env(
            'RECAPTCHA_GOOGLE_CLOUD_PROJECT_ID'
        );

        $apiKey = env(
            'RECAPTCHA_ENTERPRISE_API_KEY'
        );

        if (empty($projectId) || empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' =>
                'Konfigurasi reCAPTCHA mobile belum lengkap.',
            ], 500);
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->post(
                "https://recaptchaenterprise.googleapis.com/"
                    . "v1/projects/{$projectId}/assessments"
                    . "?key={$apiKey}",
                [
                    'event' => [
                        'token' => $request->recaptcha_token,
                        'expectedAction' => $expectedAction,
                        'userIpAddress' => $request->ip(),
                        'userAgent' => $request->userAgent(),
                    ],
                ]
            );

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' =>
                'Google tidak dapat mengesahkan reCAPTCHA mobile.',
                'google_error' => app()->isLocal()
                    ? $response->json()
                    : null,
            ], 503);
        }

        $result = $response->json();

        Log::info('Mobile reCAPTCHA', $result);

        $valid = (
            $result['tokenProperties']['valid'] ?? false
        ) === true;

        $action = $result['tokenProperties']['action'] ?? '';

        $score = (float) (
            $result['riskAnalysis']['score'] ?? 0
        );

        $minimumScore = (float) env(
            'RECAPTCHA_MIN_SCORE',
            0.5
        );

        if (
            !$valid ||
            $action !== $expectedAction ||
            $score < $minimumScore
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Pengesahan keselamatan mobile gagal.',
                'recaptcha' => app()->isLocal()
                    ? [
                        'valid' => $valid,
                        'action' => $action,
                        'score' => $score,
                        'invalid_reason' =>
                        $result['tokenProperties']['invalidReason'] ?? null,
                    ]
                    : null,
            ], 422);
        }

        return null;
    }

    private function verifyRecaptchaRequest(
        Request $request,
        string $expectedAction
    ): ?\Illuminate\Http\JsonResponse {
        if ($request->recaptcha_platform === 'mobile') {
            return $this->verifyMobileRecaptcha(
                $request,
                $expectedAction
            );
        }

        return $this->verifyRecaptcha(
            $request,
            $expectedAction
        );
    }

    private function verifyRecaptcha(
        Request $request,
        string $expectedAction
    ): ?\Illuminate\Http\JsonResponse {
        $request->validate([
            'recaptcha_token' => 'required|string',
        ]);

        $secretKey = env('RECAPTCHA_SECRET_KEY');

        if (empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi reCAPTCHA belum lengkap.',
            ], 500);
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(
                    'https://www.google.com/recaptcha/api/siteverify',
                    [
                        'secret' => $secretKey,
                        'response' => $request->recaptcha_token,
                        'remoteip' => $request->ip(),
                    ]
                );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengesahan reCAPTCHA gagal dihubungi.',
                ], 503);
            }

            $result = $response->json();

            $success = ($result['success'] ?? false) === true;
            $action = $result['action'] ?? '';
            $score = (float) ($result['score'] ?? 0);

            if (
                !$success ||
                $action !== $expectedAction ||
                $score < 0.5
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengesahan keselamatan gagal. Sila cuba semula.',
                    'recaptcha' => [
                        'success' => $success,
                        'action' => $action,
                        'score' => $score,
                        'error_codes' => $result['error-codes'] ?? [],
                    ],
                ], 422);
            }

            return null;
        } catch (\Throwable $error) {
            report($error);

            return response()->json([
                'success' => false,
                'message' => 'Ralat semasa mengesahkan reCAPTCHA.',
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $recaptchaError = $this->verifyRecaptchaRequest(
            $request,
            'signup'
        );

        if ($recaptchaError) {
            return $recaptchaError;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:lsank_users,email',
            'phone' => 'nullable|string|max:30',
            'ic_no' => 'nullable|string|max:20|unique:lsank_users,ic_no',
            'password' => 'required|string|min:6',
        ]);

        $user = LsankUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'ic_no' => $request->ic_no,
            'user_type' => 'Pengguna',
            'status' => 'active',
        ]);

        $token = $user->createToken('lsank3_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $recaptchaError = $this->verifyRecaptchaRequest(
            $request,
            'login'
        );

        if ($recaptchaError) {
            return $recaptchaError;
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = LsankUser::where(
            'email',
            $request->email
        )->first();

        if (
            !$user ||
            !Hash::check($request->password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        $token = $user
            ->createToken('lsank3_token')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    public function updatePhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully',
            'user' => $user,
        ]);
    }

    public function updateAdminProfile(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Admin profile updated successfully',
            'user' => $user,
        ]);
    }

    public function users(Request $request)
    {
        $users = LsankUser::query()
            ->select(
                'user_id',
                'name',
                'ic_no',
                'email',
                'phone',
                'user_type',
                'status',
                'created_at'
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:lsank_users,email',
            'phone' => 'nullable|string|max:30',
            'ic_no' => 'required|string|max:20|unique:lsank_users,ic_no',
            'user_type' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = LsankUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'ic_no' => $request->ic_no,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berjaya ditambah',
            'data' => $user,
        ], 201);
    }

    public function updateUser(Request $request, string $userId)
    {
        $user = LsankUser::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak dijumpai.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:lsank_users,email,' . $user->user_id . ',user_id',
            ],
            'phone' => 'nullable|string|max:30',
            'ic_no' => [
                'required',
                'string',
                'max:20',
                'unique:lsank_users,ic_no,' . $user->user_id . ',user_id',
            ],
            'user_type' => 'required|string|max:100',
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $validated['name'];
        $user->email = strtolower($validated['email']);
        $user->phone = $validated['phone'] ?? null;
        $user->ic_no = $validated['ic_no'];
        $user->user_type = $validated['user_type'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Maklumat pengguna berjaya dikemaskini.',
            'data' => $user->only([
                'user_id',
                'name',
                'ic_no',
                'email',
                'phone',
                'user_type',
                'status',
                'created_at',
            ]),
        ]);
    }

    public function deleteUser(Request $request, string $userId)
    {
        $currentUser = $request->user();

        if ((string) $currentUser->user_id === (string) $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak boleh menyahaktifkan akaun sendiri.',
            ], 422);
        }

        $user = LsankUser::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak dijumpai.',
            ], 404);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Akaun pengguna ini telah dinyahaktifkan.',
            ], 422);
        }

        $user->status = 'inactive';
        $user->save();

        // Batalkan semua token login pengguna.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akaun pengguna berjaya dinyahaktifkan.',
            'data' => [
                'user_id' => $user->user_id,
                'status' => $user->status,
            ],
        ]);
    }

    public function activateUser(Request $request, string $userId)
    {
        $user = LsankUser::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak dijumpai.',
            ], 404);
        }

        if ($user->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akaun pengguna ini sudah aktif.',
            ], 422);
        }

        $user->status = 'active';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Akaun pengguna berjaya diaktifkan semula.',
            'data' => [
                'user_id' => $user->user_id,
                'status' => $user->status,
            ],
        ]);
    }
}
