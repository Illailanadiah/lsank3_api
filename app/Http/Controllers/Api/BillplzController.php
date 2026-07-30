<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankInvoice;
use Illuminate\Http\Request;
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
         * Pastikan invois ini milik pengguna yang sedang login.
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
            strtolower(trim((string) $invoice->status))
            === 'paid'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini telah dibayar.',
            ], 422);
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

        $phone = trim((string) (
            $application?->phone
            ?? $application?->phone_no
            ?? $applicant?->phone
            ?? ''
        ));

        if ($email === '') {
            return response()->json([
                'success' => false,
                'message' =>
                'Alamat e-mel pengguna diperlukan untuk pembayaran.',
            ], 422);
        }

        /*
         * Billplz menggunakan nilai dalam sen.
         *
         * RM150.00 menjadi 15000.
         */
        $amountInCents = (int) round(
            ((float) $invoice->total_amount) * 100
        );

        try {
            $response = Http::withBasicAuth(
                config('services.billplz.secret_key'),
                ''
            )
                ->asForm()
                ->timeout(30)
                ->post(
                    rtrim(
                        config('services.billplz.api_url'),
                        '/'
                    ) . '/v3/bills',
                    [
                        'collection_id' =>
                        config(
                            'services.billplz.collection_id'
                        ),

                        'email' => $email,

                        'mobile' =>
                        $phone !== '' ? $phone : null,

                        'name' => $name,

                        'amount' => $amountInCents,

                        'description' => ($invoice->payment_type
                            ?? 'Bayaran LSANK')
                            . ' - '
                            . $invoice->invoice_no,

                        /*
                         * Buat sementara waktu kita gunakan
                         * URL placeholder dahulu.
                         *
                         * Langkah seterusnya nanti kita buat
                         * callback sebenar.
                         */
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
                    ]
                );

            if ($response->failed()) {
                Log::error('Billplz create bill failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'body' => $response->body(),
                ]);

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

            return response()->json([
                'success' => true,
                'message' =>
                'Pautan pembayaran berjaya dijana.',

                'data' => [
                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_no' =>
                    $invoice->invoice_no,

                    'bill_id' =>
                    $bill['id'] ?? null,

                    'payment_url' =>
                    $bill['url'] ?? null,

                    'amount' =>
                    (float) $invoice->total_amount,

                    'payment_status' =>
                    'pending',
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        /*
         * Kita akan lengkapkan fungsi ini
         * dalam langkah berikutnya.
         */
        Log::info('Billplz callback received', [
            'payload' => $request->all(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function redirect(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' =>
            'Pengguna telah kembali daripada Billplz.',

            'data' => $request->all(),
        ]);
    }
}
