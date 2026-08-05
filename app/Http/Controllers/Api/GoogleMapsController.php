<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsController extends Controller
{
    /**
     * Tukar latitude dan longitude kepada alamat.
     *
     * Contoh:
     * GET /api/maps/reverse-geocode?latitude=6.118400&longitude=100.368500
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ]);

        $apiKey = config('services.google_maps.api_key');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Google Maps API key belum dikonfigurasi.',
            ], 500);
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 500)
                ->get(
                    'https://maps.googleapis.com/maps/api/geocode/json',
                    [
                        'latlng' => sprintf(
                            '%s,%s',
                            $validated['latitude'],
                            $validated['longitude'],
                        ),
                        'key' => $apiKey,
                        'language' => 'ms',
                        'region' => 'my',
                    ],
                );

            if (!$response->successful()) {
                Log::warning('Google Maps reverse geocode HTTP error.', [
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps tidak dapat dihubungi.',
                ], 502);
            }

            $googleData = $response->json();
            $status = $googleData['status'] ?? null;

            if ($status === 'ZERO_RESULTS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Alamat tidak ditemui untuk koordinat ini.',
                ], 404);
            }

            if ($status !== 'OK') {
                Log::warning('Google Maps reverse geocode failed.', [
                    'google_status' => $status,
                    'error_message' => $googleData['error_message'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $this->googleErrorMessage($status),
                ], 502);
            }

            $result = $googleData['results'][0] ?? null;

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maklumat alamat tidak ditemui.',
                ], 404);
            }

            $components = $this->extractAddressComponents(
                $result['address_components'] ?? [],
            );

            return response()->json([
                'success' => true,
                'message' => 'Alamat berjaya diperoleh.',
                'data' => [
                    'formatted_address' =>
                    $result['formatted_address'] ?? null,

                    'place_id' => $result['place_id'] ?? null,

                    'latitude' => (float) $validated['latitude'],
                    'longitude' => (float) $validated['longitude'],

                    'address_line_1' => $this->buildAddressLineOne(
                        $components,
                    ),

                    'address_line_2' => $components['sublocality'],

                    'city' => $components['city'],

                    'district' => $components['district'],

                    'postcode' => $components['postcode'],

                    'state' => $components['state'],

                    'country' => $components['country'],

                    'country_code' => $components['country_code'],
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Google Maps reverse geocode exception.', [
                'message' => $exception->getMessage(),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ralat semasa mendapatkan alamat lokasi.',
            ], 500);
        }
    }

    private function extractAddressComponents(array $items): array
    {
        $components = [
            'street_number' => null,
            'route' => null,
            'premise' => null,
            'sublocality' => null,
            'city' => null,
            'district' => null,
            'postcode' => null,
            'state' => null,
            'country' => null,
            'country_code' => null,
        ];

        foreach ($items as $item) {
            $types = $item['types'] ?? [];
            $longName = $item['long_name'] ?? null;
            $shortName = $item['short_name'] ?? null;

            if (in_array('street_number', $types, true)) {
                $components['street_number'] = $longName;
            }

            if (in_array('route', $types, true)) {
                $components['route'] = $longName;
            }

            if (in_array('premise', $types, true)) {
                $components['premise'] = $longName;
            }

            if (
                in_array('sublocality', $types, true) ||
                in_array('sublocality_level_1', $types, true)
            ) {
                $components['sublocality'] = $longName;
            }

            if (in_array('locality', $types, true)) {
                $components['city'] = $longName;
            }

            if (
                in_array('administrative_area_level_2', $types, true)
            ) {
                $components['district'] = $longName;
            }

            if (
                in_array('administrative_area_level_1', $types, true)
            ) {
                $components['state'] = $longName;
            }

            if (in_array('postal_code', $types, true)) {
                $components['postcode'] = $longName;
            }

            if (in_array('country', $types, true)) {
                $components['country'] = $longName;
                $components['country_code'] = $shortName;
            }
        }

        /*
         * Sesetengah alamat Google tidak mempunyai locality.
         * Guna sublocality atau district sebagai fallback bandar.
         */
        $components['city'] ??=
            $components['sublocality'] ??
            $components['district'];

        return $components;
    }

    private function buildAddressLineOne(array $components): ?string
    {
        $parts = array_filter([
            $components['premise'],
            $components['street_number'],
            $components['route'],
        ]);

        if (empty($parts)) {
            return $components['sublocality'];
        }

        return implode(', ', array_unique($parts));
    }

    private function googleErrorMessage(?string $status): string
    {
        return match ($status) {
            'REQUEST_DENIED' =>
            'Permintaan Google Maps ditolak. Semak API key dan API restriction.',

            'OVER_DAILY_LIMIT',
            'OVER_QUERY_LIMIT' =>
            'Had penggunaan Google Maps API telah dicapai.',

            'INVALID_REQUEST' =>
            'Koordinat yang dihantar kepada Google Maps tidak sah.',

            'UNKNOWN_ERROR' =>
            'Google Maps mengalami ralat sementara. Sila cuba semula.',

            default =>
            'Alamat gagal diperoleh daripada Google Maps.',
        };
    }
}
