<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function verify(
        string $token,
        string $expectedAction,
        ?string $ipAddress = null
    ): bool {
        $secretKey = config('services.recaptcha.secret_key');
        $minimumScore = config('services.recaptcha.minimum_score', 0.5);

        if (empty($secretKey) || empty($token)) {
            return false;
        }

        $response = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ipAddress,
            ],
        );

        if (!$response->successful()) {
            return false;
        }

        $result = $response->json();

        return ($result['success'] ?? false) === true
            && ($result['action'] ?? '') === $expectedAction
            && (float) ($result['score'] ?? 0) >= (float) $minimumScore;
    }
}
