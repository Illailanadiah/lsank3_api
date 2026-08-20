<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankCivilCase;
use App\Models\LsankCriminalCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LegalCaseEmailController extends Controller
{
    public function sendPengabstrakan(
        string $type,
        $case
    ) {
        $type = strtolower(trim($type));

        if (
            !in_array(
                $type,
                ['civil', 'criminal'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis kes tidak sah.',
            ], 422);
        }

        $record = $type === 'civil'
            ? LsankCivilCase::query()
                ->with('license')
                ->findOrFail((int) $case)
            : LsankCriminalCase::query()
                ->with('license')
                ->findOrFail((int) $case);

        if (
            !str_contains(
                strtolower(
                    trim(
                        (string) $record->offence
                    )
                ),
                'pengabstrakan'
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'E-mel PIC hanya untuk kes Pengabstrakan Air.',
            ], 422);
        }

        $recipient = trim(
            (string) config(
                'legal_notifications.pengabstrakan_email',
                'illailanadiah19@gmail.com'
            )
        );

        if (
            $recipient === '' ||
            !filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Alamat e-mel PIC Pengabstrakan Air tidak sah.',
            ], 422);
        }

        $mailer = strtolower(
            trim(
                (string) config(
                    'mail.default',
                    ''
                )
            )
        );

        /*
         * "log" and "array" never deliver a real email.
         * Return an error so Flutter does not show false success.
         */
        if (
            in_array(
                $mailer,
                ['', 'log', 'array'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    "E-mel belum dihantar kerana MAIL_MAILER={$mailer}. "
                    . 'Tetapkan MAIL_MAILER=smtp dan konfigurasi akaun SMTP sebenar.',
                'recipient' => $recipient,
                'mailer' => $mailer,
            ], 422);
        }

        $label = $type === 'civil'
            ? 'Kes Sivil'
            : 'Kes Jenayah';

        $caseNo = trim(
            (string) (
                $record->case_no ?? '-'
            )
        );

        $licenseNo =
            $record->license_no
            ?? $record->license?->license_no
            ?? '-';

        $fileNo =
            $record->file_no
            ?? $record->license?->file_no
            ?? '-';

        $efilingNo =
            $type === 'criminal'
                ? (
                    $record->efiling_case_no
                    ?? '-'
                )
                : '-';

        $subject =
            "LSANK - {$label} Pengabstrakan Air - {$caseNo}";

        $body =
            "NOTIFIKASI PERUNDANGAN LSANK\n"
            . "================================\n\n"
            . "Jenis Kes: {$label}\n"
            . "Kesalahan: Pengabstrakan Air\n"
            . "No Kes: {$caseNo}\n"
            . "No Kes e-Filing: {$efilingNo}\n"
            . "Nama Pihak: "
            . ($record->party_name ?? '-')
            . "\n"
            . "No Fail Lesen: {$fileNo}\n"
            . "No Lesen: {$licenseNo}\n"
            . "Seksyen / Peraturan: "
            . (
                $record->section_regulation
                ?? '-'
            )
            . "\n"
            . "Lokasi Mahkamah: "
            . (
                $record->court_location
                ?? '-'
            )
            . "\n"
            . "Hakim: "
            . ($record->judge_name ?? '-')
            . "\n"
            . "Status Semasa: "
            . ($record->case_status ?? '-')
            . "\n\n"
            . "Catatan:\n"
            . ($record->notes ?? '-')
            . "\n\n"
            . "E-mel ini dihantar melalui sistem LSANK.";

        try {
            Mail::mailer($mailer)->raw(
                $body,
                function ($message) use (
                    $recipient,
                    $subject
                ) {
                    $message
                        ->to($recipient)
                        ->subject($subject);
                }
            );

            Log::info(
                'Pengabstrakan Air legal email sent.',
                [
                    'case_type' => $type,
                    'case_id' => (int) $case,
                    'case_no' => $caseNo,
                    'recipient' => $recipient,
                    'mailer' => $mailer,
                ]
            );

            return response()->json([
                'success' => true,
                'message' =>
                    "E-mel Pengabstrakan Air berjaya dihantar kepada {$recipient}.",
                'recipient' => $recipient,
                'mailer' => $mailer,
                'case_no' => $caseNo,
            ]);
        } catch (Throwable $error) {
            Log::error(
                'Pengabstrakan Air legal email failed.',
                [
                    'case_type' => $type,
                    'case_id' => (int) $case,
                    'case_no' => $caseNo,
                    'recipient' => $recipient,
                    'mailer' => $mailer,
                    'error' => $error->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Kes telah disimpan tetapi e-mel gagal dihantar: '
                    . $error->getMessage(),
                'recipient' => $recipient,
                'mailer' => $mailer,
            ], 500);
        }
    }
}
