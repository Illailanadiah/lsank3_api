<?php

namespace App\Services;

use App\Models\LsankApplication;
use App\Models\LsankAmendmentApplication;
use App\Models\LsankLicense;
use App\Models\LsankLicenseStatus;
use App\Models\LsankEffluentApplication;
use App\Models\LsankWaterBodyApplication;
use App\Models\LsankInvoice;
use App\Models\LsankRenewalApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        if ($application->isAmendment()) {
            return $this->applyApprovedAmendment(
                $application
            );
        }

        if (
            strtolower(
                trim(
                    (string) $application->application_category
                )
            ) === 'renewal'
        ) {
            return $this->applyApprovedRenewal(
                $application
            );
        }

        return DB::transaction(function () use ($application) {

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

            $draftData = is_array($application->draft_data)
                ? $application->draft_data
                : [];

            $draftMeta = is_array($draftData['meta'] ?? null)
                ? $draftData['meta']
                : [];

            /*
 * Tempoh lesen dipilih oleh pengguna dalam borang:
 * 1 hingga 5 tahun.
 */
            $licenseDurationYear = (int) (
                $draftData['license_duration_year']
                ?? $draftMeta['license_duration_year']
                ?? 1
            );

            /*
 * Pastikan tempoh tidak kurang daripada 1 tahun
 * dan tidak melebihi 5 tahun.
 */
            $licenseDurationYear = max(
                1,
                min($licenseDurationYear, 5)
            );

            /*
 * Lesen tamat sehari sebelum ulang tahun berikutnya.
 *
 * Contoh:
 * mula 21/07/2026
 * tempoh 1 tahun
 * tamat 20/07/2027
 */
            $expiryDate = Carbon::parse($startDate)
                ->addYears($licenseDurationYear)
                ->subDay()
                ->toDateString();

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
     * Kemas kini lesen asal bagi permohonan pindaan yang diluluskan.
     * Nombor lesen, nombor fail dan tempoh lesen dikekalkan.
     */
    public function applyApprovedAmendment(
        LsankApplication $application
    ): LsankLicense {
        return DB::transaction(function () use ($application) {
            $lockedApplication = LsankApplication::query()
                ->where(
                    'application_id',
                    $application->application_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedApplication->isAmendment()) {
                throw new RuntimeException(
                    'Permohonan ini bukan permohonan pindaan lesen.'
                );
            }

            /*
 * Jangan kemas kini lesen sebelum semua invois
 * akhir pindaan selesai dibayar.
 */
            $reviewData = is_array(
                $lockedApplication->review_data
            )
                ? $lockedApplication->review_data
                : [];

            $requiredPaymentTypes = [
                'Fi Pindaan Maklumat',
            ];

            /*
 * Fi Caj wajib dibayar jika amaunnya lebih RM0.
 */
            $chargeFee = (float) (
                $reviewData['invoice_fee_caj'] ?? 0
            );

            if ($chargeFee > 0) {
                $requiredPaymentTypes[] = 'Fi Caj';
            }

            $finalInvoices = LsankInvoice::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->whereIn(
                    'payment_type',
                    $requiredPaymentTypes
                )
                ->lockForUpdate()
                ->get();

            $existingPaymentTypes = $finalInvoices
                ->pluck('payment_type')
                ->map(
                    fn($type) => trim((string) $type)
                )
                ->unique()
                ->values();

            $missingPaymentTypes = collect(
                $requiredPaymentTypes
            )->diff($existingPaymentTypes);

            if ($missingPaymentTypes->isNotEmpty()) {
                throw new RuntimeException(
                    'Invois pindaan belum lengkap: '
                        . $missingPaymentTypes->implode(', ')
                        . '.'
                );
            }

            $unpaidPaymentTypes = $finalInvoices
                ->filter(
                    fn(LsankInvoice $invoice) =>
                    strtolower(
                        trim((string) $invoice->status)
                    ) !== 'paid'
                )
                ->pluck('payment_type')
                ->unique()
                ->values();

            if ($unpaidPaymentTypes->isNotEmpty()) {
                throw new RuntimeException(
                    'Lesen belum boleh dikemas kini. '
                        . 'Selesaikan bayaran '
                        . $unpaidPaymentTypes->implode(', ')
                        . ' terlebih dahulu.'
                );
            }

            if (!$lockedApplication->isDirectorApproved()) {
                throw new RuntimeException(
                    'Pindaan lesen hanya boleh digunakan selepas kelulusan Ketua Pengarah.'
                );
            }

            $draftData = is_array(
                $lockedApplication->draft_data
            )
                ? $lockedApplication->draft_data
                : [];

            $amendmentId = (int) (
                $draftData['amendment_id']
                ?? data_get(
                    $draftData,
                    'amendment.amendment_id'
                )
                ?? data_get(
                    $draftData,
                    'meta.amendment_id'
                )
                ?? 0
            );

            /*
            * Cari rekod pindaan berdasarkan application_id.
            */
            $amendment =
                LsankAmendmentApplication::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->lockForUpdate()
                ->first();

            /*
            * Fallback berdasarkan amendment_id dalam draft_data.
            */
            if (!$amendment && $amendmentId > 0) {
                $amendment =
                    LsankAmendmentApplication::query()
                    ->where(
                        'amendment_id',
                        $amendmentId
                    )
                    ->lockForUpdate()
                    ->first();

                if ($amendment) {
                    $amendment->application_id =
                        $lockedApplication->application_id;

                    $amendment->save();
                }
            }

            if (!$amendment) {
                throw new RuntimeException(
                    'Rekod pindaan lesen tidak dijumpai.'
                );
            }

            /*
            * Ambil lesen asal berdasarkan license_id
            * yang disimpan dalam rekod pindaan.
            */
            $license = LsankLicense::query()
                ->where(
                    'license_id',
                    $amendment->license_id
                )
                ->lockForUpdate()
                ->first();

            if (!$license) {
                throw new RuntimeException(
                    'Lesen asal untuk pindaan tidak dijumpai.'
                );
            }

            /*
            * Lesen masih dipautkan kepada application asal.
            *
            * Oleh itu, salin semua data terkini daripada
            * application pindaan ke application asal supaya:
            *
            * - License List memaparkan data baru.
            * - License Detail memaparkan data baru.
            * - Nombor lesen kekal sama.
            */
            $sourceApplication = LsankApplication::query()
                ->where(
                    'application_id',
                    $license->application_id
                )
                ->lockForUpdate()
                ->first();

            if (!$sourceApplication) {
                throw new RuntimeException(
                    'Permohonan asal lesen tidak dijumpai.'
                );
            }

            if (
                empty($amendment->source_application_id)
                || (int) $amendment->source_application_id
                !== (int) $sourceApplication->application_id
            ) {
                $amendment->source_application_id =
                    $sourceApplication->application_id;

                $amendment->save();
            }

            $this->syncAmendmentToSourceApplication(
                $lockedApplication,
                $sourceApplication
            );

            $sourceApplication->refresh();

            /*
            * Cari lesen duplicate yang pernah terhasil
            * menggunakan application pindaan ini.
            */
            $duplicateLicenseIds = LsankLicense::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->where(
                    'license_id',
                    '!=',
                    $license->license_id
                )
                ->lockForUpdate()
                ->pluck('license_id');

            if ($duplicateLicenseIds->isNotEmpty()) {
                /*
                * Pindahkan invois duplicate kepada lesen asal.
                */
                DB::table('lsank_invoices')
                    ->whereIn(
                        'license_id',
                        $duplicateLicenseIds->all()
                    )
                    ->update([
                        'license_id' =>
                        $license->license_id,

                        'updated_at' =>
                        now(),
                    ]);

                /*
                * Padam lesen duplicate.
                */
                LsankLicense::query()
                    ->whereIn(
                        'license_id',
                        $duplicateLicenseIds->all()
                    )
                    ->delete();
            }

            $draftMeta = is_array(
                $draftData['meta'] ?? null
            )
                ? $draftData['meta']
                : [];

            $selectedActivities =
                $draftData['selected_activities']
                ?? $draftMeta['selected_activities']
                ?? [];

            $selectedActivities = is_array(
                $selectedActivities
            )
                ? collect($selectedActivities)
                ->map(
                    fn($item) =>
                    trim((string) $item)
                )
                ->filter()
                ->unique()
                ->values()
                ->all()
                : [];

            $activityName = !empty($selectedActivities)
                ? implode(', ', $selectedActivities)
                : (
                    $lockedApplication->activity_name
                    ?: $lockedApplication->activity_type
                    ?: $lockedApplication->activity_details
                    ?: $license->activity_name
                    ?: '-'
                );

            /*
         * Jana QR baru untuk lesen yang dipinda.
         */
            $qrToken = (string) Str::uuid();

            $qrPayload = json_encode([
                'license_no' =>
                $license->license_no,

                /*
             * Kekalkan application asal lesen.
             */
                'application_id' =>
                $license->application_id,

                'token' =>
                $qrToken,
            ], JSON_UNESCAPED_SLASHES);

            /*
         * Update rekod lesen asal.
         *
         * Tidak mengubah:
         * - license_id
         * - license_no
         * - file_no
         * - application_id
         * - start_date
         * - expiry_date
         * - license_status_id
         */
            $license->forceFill([
                'holder_name' =>
                $lockedApplication->business_name
                    ?: $lockedApplication->company_name
                    ?: $lockedApplication->applicant_name
                    ?: $license->holder_name
                    ?: '-',

                'license_type' =>
                $lockedApplication->license_type
                    ?: $license->license_type,

                'activity_name' =>
                $activityName,

                'activity_location' =>
                $lockedApplication->activity_location
                    ?: $license->activity_location
                    ?: '-',

                'qr_token' =>
                $qrToken,

                'qr_payload_hash' =>
                hash(
                    'sha256',
                    $qrPayload ?: $qrToken
                ),

                /*
             * Buang fail lama supaya PDF dan QR
             * lesen pindaan boleh dijana semula.
             */
                'qr_code_path' => null,
                'license_pdf_path' => null,
                'generated_at' => now(),
                'pdf_downloaded_at' => null,
                'printed_at' => null,
                'qr_downloaded_at' => null,
            ])->save();

            /*
         * Tandakan proses pindaan sebagai selesai.
         */
            $newInformation = is_array(
                $amendment->new_information
            )
                ? $amendment->new_information
                : [];

            $meta = is_array(
                $newInformation['meta'] ?? null
            )
                ? $newInformation['meta']
                : [];

            $meta['completed_at'] =
                now()->toDateTimeString();

            $meta['updated_license_id'] =
                (int) $license->license_id;

            $meta['updated_license_no'] =
                (string) $license->license_no;

            $newInformation['meta'] =
                $meta;

            $newInformation['application'] =
                $lockedApplication
                ->withoutRelations()
                ->toArray();

            $newInformation['license'] =
                $license
                ->fresh()
                ->withoutRelations()
                ->toArray();

            $amendment->new_information =
                $newInformation;

            $amendment->status =
                LsankAmendmentApplication::STATUS_COMPLETED;

            $amendment->save();

            return $license->fresh();
        });
    }

    /**
     * Lengkapkan pembaharuan dengan mengemas kini
     * tempoh dan maklumat lesen asal.
     */
    public function applyApprovedRenewal(
        LsankApplication $application
    ): LsankLicense {
        return DB::transaction(function () use ($application) {
            $lockedApplication =
                LsankApplication::query()
                ->where(
                    'application_id',
                    $application->application_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                strtolower(
                    trim(
                        (string)
                        $lockedApplication
                            ->application_category
                    )
                ) !== 'renewal'
            ) {
                throw new RuntimeException(
                    'Permohonan ini bukan permohonan pembaharuan lesen.'
                );
            }

            if (
                !$lockedApplication
                    ->isDirectorApproved()
            ) {
                throw new RuntimeException(
                    'Pembaharuan lesen hanya boleh diselesaikan selepas kelulusan Ketua Pengarah.'
                );
            }

            $draftData = is_array(
                $lockedApplication->draft_data
            )
                ? $lockedApplication->draft_data
                : [];

            /*
         * Cari rekod pembaharuan berdasarkan
         * application_id pembaharuan.
         */
            $renewal =
                LsankRenewalApplication::query()
                ->where(
                    'application_id',
                    $lockedApplication
                        ->application_id
                )
                ->lockForUpdate()
                ->first();

            /*
         * Fallback menggunakan license_id
         * yang disimpan dalam draft_data.
         */
            if (!$renewal) {
                $renewalLicenseId = (int) (
                    $draftData['renewal_license_id']
                    ?? data_get(
                        $draftData,
                        'meta.renewal_license_id'
                    )
                    ?? 0
                );

                if ($renewalLicenseId > 0) {
                    $renewal =
                        LsankRenewalApplication::query()
                        ->where(
                            'license_id',
                            $renewalLicenseId
                        )
                        ->latest('renewal_id')
                        ->lockForUpdate()
                        ->first();

                    if ($renewal) {
                        $renewal->application_id =
                            $lockedApplication
                            ->application_id;

                        $renewal->save();
                    }
                }
            }

            if (!$renewal) {
                throw new RuntimeException(
                    'Rekod pembaharuan lesen tidak dijumpai.'
                );
            }

            /*
         * Ambil dan lock lesen asal.
         */
            $license =
                LsankLicense::query()
                ->where(
                    'license_id',
                    $renewal->license_id
                )
                ->lockForUpdate()
                ->first();

            if (!$license) {
                throw new RuntimeException(
                    'Lesen asal untuk pembaharuan tidak dijumpai.'
                );
            }

            /*
         * Elakkan tarikh dilanjutkan dua kali jika
         * callback pembayaran dipanggil semula.
         */
            if (
                strtolower(
                    trim(
                        (string)
                        $renewal->renewal_status
                    )
                ) ===
                LsankRenewalApplication::STATUS_COMPLETED
            ) {
                return $license->fresh();
            }

            /*
         * Pastikan invois akhir sudah selesai.
         */
            $reviewData = is_array(
                $lockedApplication->review_data
            )
                ? $lockedApplication->review_data
                : [];

            $finalInvoiceStatus = strtolower(
                trim(
                    (string) (
                        $reviewData['final_invoice_status'] ?? ''
                    )
                )
            );

            $isNoFee = in_array(
                $finalInvoiceStatus,
                [
                    'exempt',
                    'no_fee',
                ],
                true
            );

            $finalPaymentTypes = [
                'Fi Lesen',
                'Fi Caj',
                'Wang Sekuriti',
                'Fi Sekuriti',
            ];

            $finalInvoices =
                LsankInvoice::query()
                ->where(
                    'application_id',
                    $lockedApplication
                        ->application_id
                )
                ->whereIn(
                    'payment_type',
                    $finalPaymentTypes
                )
                ->lockForUpdate()
                ->get();

            if (
                !$isNoFee &&
                $finalInvoices->isEmpty()
            ) {
                throw new RuntimeException(
                    'Invois akhir pembaharuan belum dijana.'
                );
            }

            $unpaidInvoices =
                $finalInvoices->filter(
                    fn(LsankInvoice $invoice) =>
                    !in_array(
                        strtolower(
                            trim(
                                (string)
                                $invoice->status
                            )
                        ),
                        [
                            'paid',
                            'sudah_bayar',
                        ],
                        true
                    )
                );

            if ($unpaidInvoices->isNotEmpty()) {
                throw new RuntimeException(
                    'Lesen belum boleh diperbaharui. Selesaikan semua bayaran akhir terlebih dahulu.'
                );
            }

            /*
         * Dapatkan application asal yang masih
         * dipautkan kepada lesen.
         */
            $sourceApplication =
                LsankApplication::query()
                ->where(
                    'application_id',
                    $license->application_id
                )
                ->lockForUpdate()
                ->first();

            if (!$sourceApplication) {
                throw new RuntimeException(
                    'Permohonan asal lesen tidak dijumpai.'
                );
            }

            /*
         * Salin data terkini daripada permohonan
         * pembaharuan ke application asal.
         *
         * Helper ini tidak menyalin application_id,
         * status, kategori atau nombor permohonan.
         */
            $this->syncAmendmentToSourceApplication(
                $lockedApplication,
                $sourceApplication
            );

            $sourceApplication->refresh();

            /*
         * Buang lesen duplicate yang mungkin pernah
         * terhasil melalui flow lama.
         */
            $duplicateLicenseIds =
                LsankLicense::query()
                ->where(
                    'application_id',
                    $lockedApplication
                        ->application_id
                )
                ->where(
                    'license_id',
                    '!=',
                    $license->license_id
                )
                ->lockForUpdate()
                ->pluck('license_id');

            if ($duplicateLicenseIds->isNotEmpty()) {
                DB::table('lsank_invoices')
                    ->whereIn(
                        'license_id',
                        $duplicateLicenseIds->all()
                    )
                    ->update([
                        'license_id' =>
                        $license->license_id,

                        'updated_at' =>
                        now(),
                    ]);

                LsankLicense::query()
                    ->whereIn(
                        'license_id',
                        $duplicateLicenseIds->all()
                    )
                    ->delete();
            }

            /*
         * Tempoh lesen dipilih dalam borang,
         * minimum 1 tahun dan maksimum 5 tahun.
         */
            $draftMeta = is_array(
                $draftData['meta'] ?? null
            )
                ? $draftData['meta']
                : [];

            $licenseDurationYear = (int) (
                $draftData['license_duration_year']
                ?? $draftMeta['license_duration_year']
                ?? 1
            );

            $licenseDurationYear = max(
                1,
                min(
                    $licenseDurationYear,
                    5
                )
            );

            $licenseData = is_array(
                $reviewData['license'] ?? null
            )
                ? $reviewData['license']
                : [];

            /*
         * Utamakan tarikh yang ditetapkan
         * oleh pegawai semakan.
         */
            $approvedStartDate =
                $licenseData['license_start_date']
                ?? $reviewData['license_start_date']
                ?? null;

            $oldExpiryDate =
                $license->expiry_date
                ? Carbon::parse(
                    $license->expiry_date
                )->startOfDay()
                : null;

            if ($approvedStartDate) {
                $newStartDate = Carbon::parse(
                    $this->parseDate(
                        $approvedStartDate
                    )
                )->startOfDay();
            } elseif (
                $oldExpiryDate &&
                $oldExpiryDate->greaterThanOrEqualTo(
                    now()->startOfDay()
                )
            ) {
                /*
             * Lesen belum tamat:
             * tempoh baharu bermula sehari selepas
             * tarikh tamat semasa.
             */
                $newStartDate =
                    $oldExpiryDate
                    ->copy()
                    ->addDay();
            } else {
                /*
             * Lesen sudah tamat:
             * tempoh baharu bermula hari ini.
             */
                $newStartDate =
                    now()->startOfDay();
            }

            $newExpiryDate =
                $newStartDate
                ->copy()
                ->addYears(
                    $licenseDurationYear
                )
                ->subDay();

            /*
         * Pastikan status lesen Aktif wujud.
         */
            $activeStatus =
                LsankLicenseStatus::query()
                ->whereIn(
                    'status_code',
                    [
                        'active',
                        'aktif',
                    ]
                )
                ->first();

            if (!$activeStatus) {
                $activeStatus =
                    LsankLicenseStatus::query()
                    ->where(
                        'status_name',
                        'Aktif'
                    )
                    ->first();
            }

            if (!$activeStatus) {
                $activeStatus =
                    LsankLicenseStatus::create([
                        'status_name' =>
                        'Aktif',

                        'status_code' =>
                        'active',

                        'description' =>
                        'Lesen sedang aktif.',
                    ]);
            }

            /*
         * Tentukan aktiviti terkini.
         */
            $selectedActivities =
                $draftData['selected_activities']
                ?? $draftMeta['selected_activities']
                ?? [];

            $selectedActivities = is_array(
                $selectedActivities
            )
                ? collect($selectedActivities)
                ->map(
                    fn($item) =>
                    trim((string) $item)
                )
                ->filter()
                ->unique()
                ->values()
                ->all()
                : [];

            $activityName =
                !empty($selectedActivities)
                ? implode(
                    ', ',
                    $selectedActivities
                )
                : (
                    $lockedApplication
                    ->activity_name
                    ?: $lockedApplication
                    ->activity_type
                    ?: $lockedApplication
                    ->activity_details
                    ?: $license
                    ->activity_name
                    ?: '-'
                );

            /*
         * Jana QR baharu untuk tempoh lesen baharu.
         */
            $qrToken =
                (string) Str::uuid();

            $qrPayload = json_encode([
                'license_no' =>
                $license->license_no,

                'application_id' =>
                $sourceApplication
                    ->application_id,

                'token' =>
                $qrToken,
            ], JSON_UNESCAPED_SLASHES);

            /*
         * Kemas kini lesen asal.
         *
         * license_no, file_no dan application_id
         * tidak berubah.
         */
            $license->forceFill([
                'holder_name' =>
                $lockedApplication
                    ->business_name
                    ?: $lockedApplication
                    ->company_name
                    ?: $lockedApplication
                    ->applicant_name
                    ?: $license
                    ->holder_name
                    ?: '-',

                'license_type' =>
                $lockedApplication
                    ->license_type
                    ?: $license
                    ->license_type,

                'activity_name' =>
                $activityName,

                'activity_location' =>
                $lockedApplication
                    ->activity_location
                    ?: $license
                    ->activity_location
                    ?: '-',

                'start_date' =>
                $newStartDate->toDateString(),

                'expiry_date' =>
                $newExpiryDate->toDateString(),

                'license_status_id' =>
                $activeStatus
                    ->license_status_id,

                'qr_token' =>
                $qrToken,

                'qr_payload_hash' =>
                hash(
                    'sha256',
                    $qrPayload ?: $qrToken
                ),

                'qr_code_path' =>
                null,

                'license_pdf_path' =>
                null,

                'generated_at' =>
                now(),

                'pdf_downloaded_at' =>
                null,

                'printed_at' =>
                null,

                'qr_downloaded_at' =>
                null,
            ])->save();

            /*
         * Pautkan semua invois pembaharuan
         * kepada lesen asal.
         */
            LsankInvoice::query()
                ->where(
                    'application_id',
                    $lockedApplication
                        ->application_id
                )
                ->update([
                    'license_id' =>
                    $license->license_id,
                ]);

            /*
         * Lengkapkan rekod pembaharuan.
         */
            $renewal->old_expiry_date =
                $renewal->old_expiry_date
                ?? $oldExpiryDate?->toDateString();

            $renewal->new_expiry_date =
                $newExpiryDate->toDateString();

            $renewal->renewal_status =
                LsankRenewalApplication::STATUS_COMPLETED;

            $renewal->save();

            /*
         * Simpan metadata keputusan.
         */
            $reviewData['license_generation_status'] = 'updated';

            $reviewData['license_id'] =
                $license->license_id;

            $reviewData['license_no'] =
                $license->license_no;

            $reviewData['license_start_date'] =
                $newStartDate->toDateString();

            $reviewData['license_expiry_date'] =
                $newExpiryDate->toDateString();

            $reviewData['license_updated_at'] =
                now()->toDateTimeString();

            $lockedApplication->review_data =
                $reviewData;

            $lockedApplication->save();

            return $license->fresh();
        });
    }

    /**
     * Salin data application pindaan ke application asal.
     *
     * License List dan License Detail membaca application asal
     * kerana lesen asal masih menggunakan application_id lama.
     */
    private function syncAmendmentToSourceApplication(
        LsankApplication $amendmentApplication,
        LsankApplication $sourceApplication
    ): void {
        /*
        * Field yang dibenarkan untuk dikemas kini.
        *
        * Jangan salin:
        * - application_id
        * - application_ref_no
        * - application_category
        * - application_status
        * - payment_status
        *
        * Supaya identiti permohonan dan lesen asal kekal.
        */
        $fields = [
            'applicant_id',

            'applicant_name',
            'business_name',
            'phone',
            'email',

            'license_type',
            'activity_type',

            'applicant_type',
            'identity_no',
            'phone_no',
            'address',

            'company_name',
            'registration_no',
            'business_address',
            'business_phone',
            'business_email',

            'responsible_officer_name',
            'responsible_officer_phone',
            'responsible_officer_position',
            'officers',

            'activity_type_id',
            'activity_name',
            'district',
            'activity_location',
            'longitude',
            'latitude',
            'operating_days',
            'operating_time',
            'activity_details',
            'recreation_details',
            'draft_data',
            'submitted_data',
        ];

        /*
 * Hanya salin field yang benar-benar wujud
 * sebagai kolum dalam lsank_applications.
 *
 * Data seperti construction_shape hanya berada
 * dalam draft_data dan tidak boleh di-update
 * sebagai kolum terus.
 */
        $validColumns = array_flip(
            Schema::getColumnListing(
                $sourceApplication->getTable()
            )
        );

        $updates = [];

        foreach ($fields as $field) {
            if (!isset($validColumns[$field])) {
                continue;
            }

            $updates[$field] =
                $amendmentApplication->getAttribute(
                    $field
                );
        }

        $sourceApplication->forceFill(
            $updates
        )->save();

        /*
     * Sync juga jadual teknikal badan perairan.
     */
        $amendmentWaterBody =
            $amendmentApplication
            ->waterBody()
            ->first();

        if ($amendmentWaterBody) {
            LsankWaterBodyApplication::query()
                ->updateOrCreate(
                    [
                        'application_id' =>
                        $sourceApplication
                            ->application_id,
                    ],
                    [
                        'activity_type_id' =>
                        $amendmentWaterBody
                            ->activity_type_id,

                        'activity_location' =>
                        $amendmentWaterBody
                            ->activity_location,

                        'longitude' =>
                        $amendmentWaterBody
                            ->longitude,

                        'latitude' =>
                        $amendmentWaterBody
                            ->latitude,

                        'operating_days' =>
                        $amendmentWaterBody
                            ->operating_days,

                        'operating_time' =>
                        $amendmentWaterBody
                            ->operating_time,

                        'motorized_fee' =>
                        $amendmentWaterBody
                            ->motorized_fee,

                        'non_motorized_fee' =>
                        $amendmentWaterBody
                            ->non_motorized_fee,

                        'activity_details' =>
                        $amendmentWaterBody
                            ->activity_details,

                        'draft_data' =>
                        $amendmentWaterBody
                            ->draft_data,
                    ]
                );
        }

        /*
 * Sync juga jadual teknikal
 * permohonan pelepasan efluen.
 */
        $amendmentEffluent =
            $amendmentApplication
            ->effluent()
            ->first();

        if ($amendmentEffluent) {
            LsankEffluentApplication::query()
                ->updateOrCreate(
                    [
                        'application_id' =>
                        $sourceApplication
                            ->application_id,
                    ],
                    [
                        'service_type_id' =>
                        $amendmentEffluent
                            ->service_type_id,

                        'activity_location' =>
                        $amendmentEffluent
                            ->activity_location,

                        'longitude' =>
                        $amendmentEffluent
                            ->longitude,

                        'latitude' =>
                        $amendmentEffluent
                            ->latitude,

                        'composition' =>
                        $amendmentEffluent
                            ->composition,

                        'frequency' =>
                        $amendmentEffluent
                            ->frequency,

                        'flow_rate' =>
                        $amendmentEffluent
                            ->flow_rate,

                        'sampling_method' =>
                        $amendmentEffluent
                            ->sampling_method,

                        'contingency_plan' =>
                        $amendmentEffluent
                            ->contingency_plan,

                        'disposal_method' =>
                        $amendmentEffluent
                            ->disposal_method,
                    ]
                );
        }
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
