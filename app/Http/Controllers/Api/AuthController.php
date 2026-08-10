<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA Verification
    |--------------------------------------------------------------------------
    |
    | - Web uses Google reCAPTCHA siteverify.
    | - Mobile uses reCAPTCHA Enterprise assessment.
    | - On local environment only, verification is bypassed when no token is
    |   supplied. If a token is supplied locally, it will still be verified.
    |
    */

    private function verifyRecaptchaRequest(
    Request $request,
    string $expectedAction
): ?JsonResponse {
    /*
    |--------------------------------------------------------------------------
    | Local Development
    |--------------------------------------------------------------------------
    |
    | Jangan verify reCAPTCHA untuk localhost.
    | Production/staging tetap wajib verify.
    |
    */

    if (app()->isLocal()) {
        Log::debug('reCAPTCHA bypassed for local environment.', [
            'action' => $expectedAction,
            'host' => $request->getHost(),
        ]);

        return null;
    }

    $platform = strtolower(
        trim((string) $request->input('recaptcha_platform', 'web'))
    );

    if ($platform === 'mobile') {
        return $this->verifyMobileRecaptcha(
            $request,
            $expectedAction
        );
    }

    return $this->verifyWebRecaptcha(
        $request,
        $expectedAction
    );
}
    private function verifyMobileRecaptcha(
        Request $request,
        string $expectedAction
    ): ?JsonResponse {
        $request->validate([
            'recaptcha_token' => ['required', 'string'],
            'recaptcha_platform' => ['required', 'in:mobile'],
        ]);

        $projectId = env('RECAPTCHA_GOOGLE_CLOUD_PROJECT_ID');
        $apiKey = env('RECAPTCHA_ENTERPRISE_API_KEY');

        if (empty($projectId) || empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi reCAPTCHA mobile belum lengkap.',
            ], 500);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post(
                    'https://recaptchaenterprise.googleapis.com/'
                        . "v1/projects/{$projectId}/assessments"
                        . "?key={$apiKey}",
                    [
                        'event' => [
                            'token' => $request->string('recaptcha_token')->toString(),
                            'expectedAction' => $expectedAction,
                            'userIpAddress' => $request->ip(),
                            'userAgent' => $request->userAgent(),
                        ],
                    ]
                );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google tidak dapat mengesahkan reCAPTCHA mobile.',
                    'google_error' => app()->isLocal()
                        ? $response->json()
                        : null,
                ], 503);
            }

            $result = $response->json();

            Log::info('Mobile reCAPTCHA verification', [
                'expected_action' => $expectedAction,
                'result' => $result,
            ]);

            $valid = (
                $result['tokenProperties']['valid'] ?? false
            ) === true;

            $action = (string) (
                $result['tokenProperties']['action'] ?? ''
            );

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
                    'message' => 'Pengesahan keselamatan mobile gagal.',
                    'recaptcha' => app()->isLocal()
                        ? [
                            'valid' => $valid,
                            'action' => $action,
                            'score' => $score,
                            'minimum_score' => $minimumScore,
                            'invalid_reason' =>
                                $result['tokenProperties']['invalidReason']
                                ?? null,
                        ]
                        : null,
                ], 422);
            }

            return null;
        } catch (\Throwable $error) {
            report($error);

            return response()->json([
                'success' => false,
                'message' => 'Ralat semasa mengesahkan reCAPTCHA mobile.',
            ], 500);
        }
    }

    private function verifyWebRecaptcha(
        Request $request,
        string $expectedAction
    ): ?JsonResponse {
        $request->validate([
            'recaptcha_token' => ['required', 'string'],
            'recaptcha_platform' => ['nullable', 'in:web'],
        ]);

        $secretKey = env('RECAPTCHA_SECRET_KEY');

        if (empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi reCAPTCHA web belum lengkap.',
            ], 500);
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(
                    'https://www.google.com/recaptcha/api/siteverify',
                    [
                        'secret' => $secretKey,
                        'response' => $request->string('recaptcha_token')->toString(),
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

            Log::info('Web reCAPTCHA verification', [
                'expected_action' => $expectedAction,
                'result' => $result,
            ]);

            $success = ($result['success'] ?? false) === true;
            $action = (string) ($result['action'] ?? '');
            $score = (float) ($result['score'] ?? 0);
            $minimumScore = (float) env('RECAPTCHA_MIN_SCORE', 0.5);

            if (
                !$success ||
                $action !== $expectedAction ||
                $score < $minimumScore
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengesahan keselamatan gagal. Sila cuba semula.',
                    'recaptcha' => app()->isLocal()
                        ? [
                            'success' => $success,
                            'action' => $action,
                            'score' => $score,
                            'minimum_score' => $minimumScore,
                            'error_codes' => $result['error-codes'] ?? [],
                        ]
                        : null,
                ], 422);
            }

            return null;
        } catch (\Throwable $error) {
            report($error);

            return response()->json([
                'success' => false,
                'message' => 'Ralat semasa mengesahkan reCAPTCHA web.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

   public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email',
            'max:255',
            'unique:lsank_users,email',
        ],
        'phone' => ['nullable', 'string', 'max:30'],
        'ic_no' => [
            'nullable',
            'string',
            'max:20',
            'unique:lsank_users,ic_no',
        ],
        'password' => ['required', 'string', 'min:6'],

        'recaptcha_token' => ['nullable', 'string'],
        'recaptcha_platform' => ['nullable', 'in:web,mobile'],
    ]);

    $recaptchaError = $this->verifyRecaptchaRequest(
        $request,
        'signup'
    );

    if ($recaptchaError) {
        return $recaptchaError;
    }

    $user = LsankUser::create([
        'name' => trim($validated['name']),
        'email' => strtolower(trim($validated['email'])),
        'phone' => isset($validated['phone'])
            ? trim($validated['phone'])
            : null,
        'ic_no' => isset($validated['ic_no'])
            ? $this->normalizeIc($validated['ic_no'])
            : null,
        'password' => Hash::make($validated['password']),
        'user_type' => 'Pengguna',
        'status' => 'active',
    ]);

    $token = $user
        ->createToken('lsank3_token')
        ->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Registration successful.',
        'token' => $token,
        'user' => $user,
    ], 201);
}

   public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],

        // Optional pada validation awal.
        // Production akan diwajibkan dalam verifyWebRecaptcha /
        // verifyMobileRecaptcha.
        'recaptcha_token' => ['nullable', 'string'],
        'recaptcha_platform' => ['nullable', 'in:web,mobile'],
    ]);

    $recaptchaError = $this->verifyRecaptchaRequest(
        $request,
        'login'
    );

    if ($recaptchaError) {
        return $recaptchaError;
    }

    $email = strtolower(trim($validated['email']));

    $user = LsankUser::query()
        ->whereRaw('LOWER(email) = ?', [$email])
        ->first();

    if (
        !$user ||
        !Hash::check($validated['password'], $user->password)
    ) {
        throw ValidationException::withMessages([
            'email' => ['E-mel atau kata laluan tidak sah.'],
        ]);
    }

    if (
        strtolower(trim((string) $user->status)) !== 'active'
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Akaun anda tidak aktif.',
        ], 403);
    }

    $token = $user
        ->createToken('lsank3_token')
        ->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Login berjaya.',
        'token' => $token,
        'user' => $user,
    ]);
}

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berjaya.',
        ]);
    }

    public function updatePhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $user->phone = trim($validated['phone']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Nombor telefon berjaya dikemaskini.',
            'user' => $user->fresh(),
        ]);
    }

    public function updateAdminProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $user->phone = trim($validated['phone']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil pentadbir berjaya dikemaskini.',
            'user' => $user->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    public function users(Request $request): JsonResponse
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
                'created_at',
                'updated_at'
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:lsank_users,email',
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'ic_no' => [
                'required',
                'string',
                'max:20',
                'unique:lsank_users,ic_no',
            ],
            'user_type' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = LsankUser::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => isset($validated['phone'])
                ? trim($validated['phone'])
                : null,
            'ic_no' => $this->normalizeIc($validated['ic_no']),
            'password' => Hash::make($validated['password']),
            'user_type' => trim($validated['user_type']),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berjaya ditambah.',
            'data' => $user,
        ], 201);
    }

    public function updateUser(
        Request $request,
        int $userId
    ): JsonResponse {
        $user = $this->findUserById($userId);

        if (!$user) {
            return $this->userNotFoundResponse();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('lsank_users', 'email')
                    ->ignore($userId, 'user_id'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'ic_no' => [
                'required',
                'string',
                'max:20',
                Rule::unique('lsank_users', 'ic_no')
                    ->ignore($userId, 'user_id'),
            ],
            'user_type' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));
        $user->phone = isset($validated['phone'])
            ? trim($validated['phone'])
            : null;
        $user->ic_no = $this->normalizeIc($validated['ic_no']);
        $user->user_type = trim($validated['user_type']);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Maklumat pengguna berjaya dikemaskini.',
            'data' => $user->fresh(),
        ]);
    }

    public function deleteUser(
        Request $request,
        int $userId
    ): JsonResponse {
        $user = $this->findUserById($userId);

        if (!$user) {
            return $this->userNotFoundResponse();
        }

        $authenticatedUserId = (string) (
            $request->user()?->user_id
            ?? $request->user()?->getKey()
            ?? ''
        );

        if ($authenticatedUserId === (string) $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak boleh menyahaktifkan akaun sendiri.',
            ], 422);
        }

        if (
            strtolower(trim((string) $user->status)) === 'inactive'
        ) {
            return response()->json([
                'success' => true,
                'message' => 'Akaun pengguna telah pun dinyahaktifkan.',
                'data' => $user,
            ]);
        }

        $user->status = 'inactive';
        $user->save();

        // Revoke all active Sanctum tokens immediately.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akaun pengguna berjaya dinyahaktifkan.',
            'data' => $user->fresh(),
        ]);
    }

    public function activateUser(
        Request $request,
        int $userId
    ): JsonResponse {
        $user = $this->findUserById($userId);

        if (!$user) {
            return $this->userNotFoundResponse();
        }

        if (
            strtolower(trim((string) $user->status)) === 'active'
        ) {
            return response()->json([
                'success' => true,
                'message' => 'Akaun pengguna telah pun aktif.',
                'data' => $user,
            ]);
        }

        $user->status = 'active';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Akaun pengguna berjaya diaktifkan semula.',
            'data' => $user->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function findUserById(int $userId): ?LsankUser
    {
        return LsankUser::query()
            ->where('user_id', $userId)
            ->first();
    }

    private function normalizeIc(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    private function userNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pengguna tidak dijumpai.',
        ], 404);
    }
}
