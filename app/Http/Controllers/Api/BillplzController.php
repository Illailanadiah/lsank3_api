<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankInvoice;
use App\Models\LsankPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillplzController extends Controller
{
    public function createBill(
        Request $request,
        LsankInvoice $invoice
    ) {
        /*
         * Pastikan invois milik pengguna yang sedang login.
         */
        if (
            (int) $invoice->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois tidak dijumpai.',
            ], 404);
        }

        /*
         * Jangan cipta Billplz jika invois sudah dibayar.
         */
        if (
            in_array(
                strtolower(trim((string) $invoice->status)),
                ['paid', 'sudah_bayar'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini telah dibayar.',
            ], 422);
        }

        /*
         * Jika Billplz bill pending sudah wujud,
         * pulangkan URL yang sama.
         */
        $existingPayment = LsankPayment::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->whereNotNull('billplz_bill_id')
            ->whereNotNull('billplz_payment_url')
            ->latest('payment_id')
            ->first();

        if (
            $existingPayment &&
            in_array(
                strtolower(
                    trim(
                        (string) $existingPayment->payment_status
                    )
                ),
                ['pending', 'unpaid'],
                true
            )
        ) {
            return response()->json([
                'success' => true,
                'message' =>
                'Pautan pembayaran sedia ada digunakan.',

                'data' => [
                    'payment_id' =>
                    $existingPayment->payment_id,

                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_no' =>
                    $invoice->invoice_no,

                    'bill_id' =>
                    $existingPayment->billplz_bill_id,

                    'payment_url' =>
                    $existingPayment->billplz_payment_url,

                    'amount' =>
                    (float) $invoice->total_amount,

                    'payment_status' =>
                    $existingPayment->payment_status,
                ],
            ]);
        }

        $invoice->load([
            'application.applicant',
        ]);

        $application = $invoice->application;
        $applicant = $application?->applicant;
        $user = $request->user();

        $name = trim((string) (
            $application?->applicant_name
            ?? $applicant?->applicant_name
            ?? $user->name
            ?? 'Pengguna LSANK'
        ));

        $email = trim((string) (
            $application?->email
            ?? $applicant?->email
            ?? $user->email
            ?? ''
        ));

        /*
         * Normalisasi nombor telefon Malaysia.
         *
         * Contoh:
         * 0194773561 -> 60194773561
         */
        $rawPhone = trim((string) (
            $application?->phone
            ?? $application?->phone_no
            ?? $applicant?->phone_no
            ?? $user->phone
            ?? ''
        ));

        $phone = preg_replace(
            '/\D+/',
            '',
            $rawPhone
        );

        if (
            $phone !== '' &&
            str_starts_with($phone, '0')
        ) {
            $phone = '6' . $phone;
        }

        /*
         * Jika format tidak sah, jangan hantar mobile.
         * Email masih digunakan sebagai contact utama.
         */
        if (
            $phone !== '' &&
            !preg_match('/^60\d{9,10}$/', $phone)
        ) {
            $phone = '';
        }

        if ($email === '') {
            return response()->json([
                'success' => false,
                'message' =>
                'Alamat e-mel pengguna diperlukan untuk pembayaran.',
            ], 422);
        }

        $secretKey = trim((string) config(
            'services.billplz.secret_key'
        ));

        $collectionId = trim((string) config(
            'services.billplz.collection_id'
        ));

        $apiUrl = rtrim(
            trim((string) config(
                'services.billplz.api_url'
            )),
            '/'
        );

        if (
            $secretKey === '' ||
            $collectionId === '' ||
            $apiUrl === ''
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Konfigurasi Billplz belum lengkap.',
            ], 500);
        }

        /*
         * Billplz menggunakan nilai dalam sen.
         *
         * RM150.00 menjadi 15000.
         */
        $amountInCents = (int) round(
            ((float) $invoice->total_amount) * 100
        );

        if ($amountInCents < 100) {
            return response()->json([
                'success' => false,
                'message' =>
                'Jumlah pembayaran minimum ialah RM1.00.',
            ], 422);
        }

        $payload = [
            'collection_id' => $collectionId,

            'email' => $email,

            'name' => $name,

            'amount' => $amountInCents,

            'description' => ($invoice->payment_type
                ?? 'Bayaran LSANK')
                . ' - '
                . $invoice->invoice_no,

            'callback_url' =>
            url('/api/billplz/callback'),

            'redirect_url' =>
            url('/api/billplz/redirect'),

            'reference_1_label' =>
            'Invoice No',

            'reference_1' =>
            $invoice->invoice_no,

            'reference_2_label' =>
            'Application No',

            'reference_2' =>
            $application?->application_ref_no
                ?? '-',

            'deliver' => false,
        ];

        if ($phone !== '') {
            $payload['mobile'] = $phone;
        }

        try {
            $response = Http::withBasicAuth(
                $secretKey,
                ''
            )
                ->asForm()
                ->acceptJson()
                ->timeout(30)
                ->post(
                    $apiUrl . '/v3/bills',
                    $payload
                );

            if ($response->failed()) {
                Log::error(
                    'Billplz create bill failed',
                    [
                        'invoice_id' =>
                        $invoice->invoice_id,

                        'status' =>
                        $response->status(),

                        'response' =>
                        $response->json(),

                        'body' =>
                        $response->body(),
                    ]
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                    data_get(
                        $response->json(),
                        'error.message'
                    )
                        ?? 'Billplz gagal mencipta bil.',

                    'billplz_error' =>
                    $response->json(),
                ], 422);
            }

            $bill = $response->json();

            $billId = trim((string) (
                $bill['id'] ?? ''
            ));

            $paymentUrl = trim((string) (
                $bill['url'] ?? ''
            ));

            if (
                $billId === '' ||
                $paymentUrl === ''
            ) {
                Log::error(
                    'Billplz response incomplete',
                    [
                        'invoice_id' =>
                        $invoice->invoice_id,

                        'response' =>
                        $bill,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                    'Respons Billplz tidak lengkap.',
                ], 502);
            }

            $payment = DB::transaction(
                function () use (
                    $invoice,
                    $billId,
                    $paymentUrl,
                    $bill
                ) {
                    return LsankPayment::updateOrCreate(
                        [
                            'invoice_id' =>
                            $invoice->invoice_id,
                        ],
                        [
                            'amount' =>
                            $invoice->total_amount,

                            'payment_status' =>
                            'pending',

                            'payment_date' =>
                            null,

                            'transaction_ref_no' =>
                            $billId,

                            'billplz_bill_id' =>
                            $billId,

                            'billplz_payment_url' =>
                            $paymentUrl,

                            'billplz_create_response' =>
                            $bill,

                            'billplz_callback_payload' =>
                            null,

                            'billplz_callback_received_at' =>
                            null,
                        ]
                    );
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                'Pautan pembayaran berjaya dijana.',

                'data' => [
                    'payment_id' =>
                    $payment->payment_id,

                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_no' =>
                    $invoice->invoice_no,

                    'bill_id' =>
                    $billId,

                    'payment_url' =>
                    $paymentUrl,

                    'amount' =>
                    (float) $invoice->total_amount,

                    'payment_status' =>
                    $payment->payment_status,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Billplz create bill exception',
                [
                    'invoice_id' =>
                    $invoice->invoice_id,

                    'message' =>
                    $e->getMessage(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                'Ralat berlaku semasa menjana pautan pembayaran.',
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        $payload = $request->all();

        Log::info(
            'Billplz callback received',
            [
                'payload' => $payload,
            ]
        );

        if (
            !$this->verifyBillplzSignature($payload)
        ) {
            Log::warning(
                'Invalid Billplz callback signature',
                [
                    'bill_id' =>
                    $payload['id'] ?? null,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                'Invalid Billplz signature.',
            ], 403);
        }

        $billId = trim((string) (
            $payload['id'] ?? ''
        ));

        $paid = filter_var(
            $payload['paid'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $state = strtolower(
            trim((string) (
                $payload['state'] ?? ''
            ))
        );

        if ($billId === '') {
            return response()->json([
                'success' => false,
                'message' =>
                'Bill ID tidak diterima.',
            ], 422);
        }

        $payment = LsankPayment::query()
            ->where(
                'billplz_bill_id',
                $billId
            )
            ->with('invoice')
            ->first();

        if (!$payment) {
            Log::error(
                'Billplz payment not found',
                [
                    'bill_id' => $billId,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                'Rekod pembayaran tidak dijumpai.',
            ], 404);
        }

        /*
         * Callback Billplz boleh dihantar semula.
         * Elakkan pembayaran diproses dua kali.
         */
        if (
            $paid &&
            in_array(
                strtolower(
                    trim(
                        (string) $payment->payment_status
                    )
                ),
                ['successful', 'success', 'paid'],
                true
            )
        ) {
            return response()->json([
                'success' => true,
                'message' =>
                'Pembayaran telah diproses sebelum ini.',
            ]);
        }

        DB::transaction(function () use (
            $payment,
            $payload,
            $paid,
            $state
        ) {
            $paymentStatus = match (true) {
                $paid => 'successful',
                $state === 'due' => 'pending',
                default => 'failed',
            };

            $payment->update([
                'payment_status' =>
                $paymentStatus,

                'payment_date' =>
                $paid ? now() : null,

                'transaction_ref_no' =>
                $payload['transaction_id']
                    ?? $payment->billplz_bill_id,

                'billplz_callback_payload' =>
                $payload,

                'billplz_callback_received_at' =>
                now(),
            ]);

            if (
                $paid &&
                $payment->invoice
            ) {
                $payment->invoice->update([
                    'status' => 'paid',
                ]);
            }
        });

        /*
         * Langkah seterusnya:
         *
         * 1. Cipta resit.
         * 2. Update status permohonan.
         * 3. Teruskan flow fi pemprosesan.
         * 4. Jana lesen jika semua invoice akhir dibayar.
         */

        return response()->json([
            'success' => true,
            'message' =>
            'Callback Billplz berjaya diproses.',
        ]);
    }

    public function redirect(Request $request)
    {
        $billId = trim((string) (
            $request->input('billplz.id')
            ?? $request->input('id')
            ?? ''
        ));

        $payment = null;

        if ($billId !== '') {
            $payment = LsankPayment::query()
                ->where('billplz_bill_id', $billId)
                ->with('invoice')
                ->first();
        }

        $frontendUrl = rtrim(
            (string) config(
                'services.frontend_url',
                'http://localhost:3000'
            ),
            '/'
        );

        $verificationUrl = $frontendUrl
            . '/payment-verification'
            . '?billId='
            . urlencode($billId)
            . '&invoiceId='
            . urlencode(
                (string) ($payment?->invoice_id ?? '')
            );

        return redirect()->away($verificationUrl);
    }

    public function status(
        Request $request,
        LsankInvoice $invoice
    ) {
        if (
            (int) $invoice->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois tidak dijumpai.',
            ], 404);
        }

        $payment = LsankPayment::query()
            ->where('invoice_id', $invoice->invoice_id)
            ->latest('payment_id')
            ->first();

        $invoiceStatus = strtolower(
            trim((string) $invoice->status)
        );

        $paymentStatus = strtolower(
            trim((string) ($payment?->payment_status ?? ''))
        );

        $paid = in_array(
            $invoiceStatus,
            ['paid', 'sudah_bayar'],
            true
        ) || in_array(
            $paymentStatus,
            ['successful', 'success', 'paid'],
            true
        );

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => $invoice->invoice_id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_status' => $invoice->status,
                'payment_id' => $payment?->payment_id,
                'payment_status' =>
                $payment?->payment_status,
                'bill_id' =>
                $payment?->billplz_bill_id,
                'paid' => $paid,
            ],
        ]);
    }

    private function verifyBillplzSignature(
        array $payload
    ): bool {
        $receivedSignature = trim((string) (
            $payload['x_signature']
            ?? $payload['x-signature']
            ?? ''
        ));

        $signatureKey = trim((string) config(
            'services.billplz.x_signature_key'
        ));

        if (
            $receivedSignature === '' ||
            $signatureKey === ''
        ) {
            return false;
        }

        unset(
            $payload['x_signature'],
            $payload['x-signature']
        );

        /*
         * Susun key secara case-insensitive.
         */
        uksort(
            $payload,
            static fn(
                string $left,
                string $right
            ): int => strcasecmp(
                $left,
                $right
            )
        );

        $source = collect($payload)
            ->map(
                static function (
                    mixed $value,
                    string $key
                ): string {
                    if (is_bool($value)) {
                        $value = $value
                            ? 'true'
                            : 'false';
                    }

                    if (is_array($value)) {
                        $value = json_encode(
                            $value,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                        );
                    }

                    return $key . $value;
                }
            )
            ->implode('|');

        $calculatedSignature = hash_hmac(
            'sha256',
            $source,
            $signatureKey
        );

        return hash_equals(
            $calculatedSignature,
            $receivedSignature
        );
    }
}
