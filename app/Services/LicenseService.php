<?php

namespace App\Services;

use App\Models\LsankApplication;
use App\Models\LsankLicense;
use App\Models\LsankLicenseStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LicenseService
{
    /**
     * Jana rekod lesen selepas permohonan diluluskan
     * oleh Ketua Pengarah.
     */
    public function generateForApprovedApplication(
        LsankApplication $application
    ): LsankLicense {
        return DB::transaction(function () use ($application) {
            $application->refresh();

            /*
             * Pastikan hanya permohonan yang telah diluluskan
             * oleh Ketua Pengarah boleh menghasilkan lesen.
             */
            if (!$application->isDirectorApproved()) {
                throw new RuntimeException(
                    'Lesen hanya boleh dijana selepas kelulusan Ketua Pengarah.'
                );
            }

            /*
             * Elakkan lesen berganda apabila butang Lulus
             * ditekan lebih daripada sekali.
             */
            $existingLicense = LsankLicense::where(
                'application_id',
                $application->application_id
            )->first();

            if ($existingLicense) {
                return $existingLicense;
            }

            /*
             * Cari status lesen Aktif.
             */
            $activeStatus = LsankLicenseStatus::query()
                ->whereIn('status_code', [
                    'active',
                    'aktif',
                ])
                ->first();

            if (!$activeStatus) {
                $activeStatus = LsankLicenseStatus::where(
                    'status_name',
                    'Aktif'
                )->first();
            }

            /*
             * Cipta status Aktif jika belum wujud.
             */
            if (!$activeStatus) {
                $activeStatus = LsankLicenseStatus::create([
                    'status_name' => 'Aktif',
                    'status_code' => 'active',
                    'description' => 'Lesen sedang aktif.',
                ]);
            }

            $reviewData = is_array($application->review_data)
                ? $application->review_data
                : [];

            $licenseData = is_array(
                $reviewData['license'] ?? null
            )
                ? $reviewData['license']
                : [];

            /*
             * Ambil tarikh yang telah ditetapkan dalam
             * borang semakan. Jika kosong, lesen bermula hari ini
             * dan tamat selepas satu tahun.
             */
            $startDateValue =
                $licenseData['license_start_date']
                ?? $reviewData['license_start_date']
                ?? now()->toDateString();

            $startDate = $this->parseDate($startDateValue);

            $expiryDateValue =
                $licenseData['license_end_date']
                ?? $reviewData['license_end_date']
                ?? Carbon::parse($startDate)
                ->addYear()
                ->subDay()
                ->toDateString();

            $expiryDate = $this->parseDate($expiryDateValue);

            $licenseNo = $this->generateLicenseNo(
                $application
            );

            $qrToken = (string) Str::uuid();

            $qrPayload = json_encode([
                'license_no' => $licenseNo,
                'application_id' =>
                $application->application_id,
                'token' => $qrToken,
            ], JSON_UNESCAPED_SLASHES);

            return LsankLicense::create([
                'license_no' => $licenseNo,

                'file_no' =>
                $application->application_ref_no,

                'application_id' =>
                $application->application_id,

                'holder_name' =>
                $application->business_name
                    ?: $application->company_name
                    ?: $application->applicant_name
                    ?: '-',

                'license_type' =>
                $application->license_type
                    ?: (
                        $application->isEffluentApplication()
                        ? 'Aktiviti Pelepasan Efluen'
                        : 'Aktiviti Badan Perairan'
                    ),

                'activity_name' =>
                $application->activity_name
                    ?: $application->activity_type
                    ?: $application->activity_details
                    ?: '-',

                'activity_location' =>
                $application->activity_location
                    ?: '-',

                'start_date' => $startDate,
                'expiry_date' => $expiryDate,

                'license_status_id' =>
                $activeStatus->license_status_id,

                'qr_token' => $qrToken,

                'qr_payload_hash' => hash(
                    'sha256',
                    $qrPayload ?: $qrToken
                ),

                /*
                 * Laluan QR dan PDF akan diisi kemudian
                 * apabila kita sambungkan generator QR/PDF.
                 */
                'qr_code_path' => null,
                'license_pdf_path' => null,

                'generated_at' => now(),
            ]);
        });
    }

    /**
     * Jana nombor lesen berdasarkan jenis permohonan.
     */
    private function generateLicenseNo(
        LsankApplication $application
    ): string {
        $year = now()->format('Y');

        $runningNumber = str_pad(
            (string) $application->application_id,
            6,
            '0',
            STR_PAD_LEFT
        );

        $typeCode = $application->isEffluentApplication()
            ? 'EFL'
            : 'BP';

        return "LSANK/{$typeCode}/{$year}/{$runningNumber}";
    }

    /**
     * Tukarkan tarikh daripada format biasa kepada Y-m-d.
     */
    private function parseDate(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return now()->toDateString();
        }

        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $text
                )->toDateString();
            } catch (\Throwable) {
                // Cuba format berikutnya.
            }
        }

        return Carbon::parse($text)->toDateString();
    }
}
