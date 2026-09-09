<?php

namespace App\Services;

use App\Models\LsankApplication;
use App\Models\LsankCompound;
use App\Models\LsankInvoice;
use App\Models\LsankLicense;
use App\Models\LsankNotice;
use App\Models\LsankNoticeRestriction;
use App\Models\LsankPayment;
use App\Models\LsankUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompoundService
{
    /*
    |--------------------------------------------------------------------------
    | ISSUE COMPOUND
    |--------------------------------------------------------------------------
    |
    | One successful transaction creates:
    |
    | 1. Compound
    | 2. Compound invoice
    | 3. NPP notice
    | 4. Notice restriction
    |
    | If one fails, everything is rolled back.
    |
    */

    public function issue(
        int $inspectionReportId,
        array $data,
        LsankUser $actor
    ): LsankCompound {
        $compound = DB::transaction(
            function () use (
                $inspectionReportId,
                $data,
                $actor
            ) {
                /*
                |--------------------------------------------------------------------------
                | LOCK INSPECTION REPORT
                |--------------------------------------------------------------------------
                */

                $report = DB::table(
                    'lsank_inspection_reports'
                )
                    ->where(
                        'report_id',
                        $inspectionReportId
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $report) {
                    throw new \RuntimeException(
                        'Laporan pemeriksaan tidak dijumpai.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | PREVENT DUPLICATE COMPOUND
                |--------------------------------------------------------------------------
                */

                $existingCompound = LsankCompound::query()
                    ->where(
                        'inspection_report_id',
                        $inspectionReportId
                    )
                    ->where(
                        'workflow_status',
                        '!=',
                        LsankCompound::STATUS_CANCELLED
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existingCompound) {
                    throw new \RuntimeException(
                        'Kompaun bagi laporan pemeriksaan ini telah dijana.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RESOLVE LICENSE
                |--------------------------------------------------------------------------
                */

                if (
                    empty($report->license_id)
                ) {
                    throw new \RuntimeException(
                        'Laporan pemeriksaan tidak mempunyai maklumat lesen.'
                    );
                }

                $license = LsankLicense::query()
                    ->where(
                        'license_id',
                        $report->license_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $license) {
                    throw new \RuntimeException(
                        'Lesen bagi laporan pemeriksaan tidak dijumpai.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RESOLVE APPLICATION
                |--------------------------------------------------------------------------
                */

                if (
                    empty($license->application_id)
                ) {
                    throw new \RuntimeException(
                        'Lesen tidak mempunyai pautan kepada permohonan.'
                    );
                }

                $application = LsankApplication::query()
                    ->where(
                        'application_id',
                        $license->application_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $application) {
                    throw new \RuntimeException(
                        'Permohonan berkaitan lesen tidak dijumpai.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | RESOLVE HOLDER
                |--------------------------------------------------------------------------
                */

                if (
                    empty($application->user_id)
                ) {
                    throw new \RuntimeException(
                        'Permohonan tidak mempunyai pengguna yang sah.'
                    );
                }

                $holder = LsankUser::query()
                    ->where(
                        'user_id',
                        $application->user_id
                    )
                    ->first();

                if (! $holder) {
                    throw new \RuntimeException(
                        'Pemegang lesen tidak dijumpai.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DATES
                |--------------------------------------------------------------------------
                */

                $issuedAt = now();

                /*
                 * Holder is given exactly 14 days.
                 *
                 * Example:
                 * issued 27/08/2026
                 * due    10/09/2026 23:59:59
                 */
                $dueAt = $issuedAt
                    ->copy()
                    ->addDays(14)
                    ->endOfDay();

                /*
                |--------------------------------------------------------------------------
                | COMPOUND DATA
                |--------------------------------------------------------------------------
                */

                $amount = $this->normalizeAmount(
                    $data['amount'] ?? null
                );

                if ($amount <= 0) {
                    throw new \RuntimeException(
                        'Amaun kompaun mestilah melebihi RM0.00.'
                    );
                }

                $partyName = $this->firstValue([
                    $data['party_name'] ?? null,
                    $report->holder_name ?? null,
                    $license->holder_name ?? null,
                    $application->business_name ?? null,
                    $application->company_name ?? null,
                    $application->applicant_name ?? null,
                    $holder->name ?? null,
                ]);

                $registerNo = $this->firstValue([
                    $data['register_no'] ?? null,
                    $report->registration_no ?? null,
                    $application->registration_no ?? null,
                    $application->identity_no ?? null,
                    $holder->ic_no ?? null,
                ]);

                $fileNo = $this->firstValue([
                    $data['file_no'] ?? null,
                    $report->file_no ?? null,
                    $license->file_no ?? null,
                    $application->application_ref_no ?? null,
                ]);

                $licenseNo = $this->firstValue([
                    $data['license_no'] ?? null,
                    $report->license_no ?? null,
                    $license->license_no ?? null,
                ]);

                $compoundType = $this->firstValue([
                    $data['compound_type'] ?? null,
                    $application->activity_name ?? null,
                    $application->activity_type ?? null,
                    $license->activity_name ?? null,
                    $license->license_type ?? null,
                    $report->inspection_type ?? null,
                ]);

                $district = $this->firstValue([
                    $data['district'] ?? null,
                    $application->district ?? null,
                ]);

                $location = $this->firstValue([
                    $data['location'] ?? null,
                    $report->location ?? null,
                    $application->activity_location ?? null,
                    $license->activity_location ?? null,
                ]);

                $sectionRegulation = $this->firstValue([
                    $data['section_regulation'] ?? null,
                    $data['offence_section'] ?? null,
                    $report->offence_section ?? null,
                ]);

                $description = $this->firstValue([
                    $data['offence'] ?? null,
                    $data['description'] ?? null,
                    $report->inspection_result ?? null,
                    'Ketidakpatuhan dikesan melalui pemeriksaan penguatkuasaan.',
                ]);

                /*
                |--------------------------------------------------------------------------
                | CREATE COMPOUND
                |--------------------------------------------------------------------------
                */

                $compound = LsankCompound::create([
                    'compound_no' =>
                        $this->generateCompoundNumber(),

                    'inspection_report_id' =>
                        $inspectionReportId,

                    'license_id' =>
                        $license->license_id,

                    'application_id' =>
                        $application->application_id,

                    'user_id' =>
                        $holder->user_id,

                    'file_no' =>
                        $fileNo,

                    'license_no' =>
                        $licenseNo,

                    'party_name' =>
                        $partyName,

                    'register_no' =>
                        $registerNo,

                    'compound_type' =>
                        $compoundType,

                    'district' =>
                        $district,

                    'location' =>
                        $location,

                    'section_regulation' =>
                        $sectionRegulation,

                    /*
                     * Legacy columns remain nullable.
                     */
                    'offence_type_id' =>
                        $data['offence_type_id'] ?? null,

                    'compound_status_id' =>
                        $data['compound_status_id'] ?? null,

                    'compound_date' =>
                        $issuedAt->toDateString(),

                    'amount' =>
                        $amount,

                    'description' =>
                        $description,

                    'workflow_status' =>
                        LsankCompound::STATUS_ISSUED,

                    'issued_at' =>
                        $issuedAt,

                    'due_at' =>
                        $dueAt,

                    'created_by' =>
                        $actor->user_id,

                    'updated_by' =>
                        $actor->user_id,
                ]);

                /*
                |--------------------------------------------------------------------------
                | CREATE INVOICE
                |--------------------------------------------------------------------------
                */

                $invoice = LsankInvoice::create([
                    'invoice_no' =>
                        $this->generateInvoiceNumber(),

                    'application_id' =>
                        $application->application_id,

                    'license_id' =>
                        $license->license_id,

                    'user_id' =>
                        $holder->user_id,

                    'invoice_date' =>
                        $issuedAt->toDateString(),

                    'due_date' =>
                        $dueAt->toDateString(),

                    /*
                     * Existing LSANK invoice status uses:
                     * unpaid / paid
                     */
                    'payment_type' =>
                        'Kompaun',

                    'total_amount' =>
                        $amount,

                    'status' =>
                        'unpaid',
                ]);

                /*
                |--------------------------------------------------------------------------
                | CREATE NPP NOTICE
                |--------------------------------------------------------------------------
                */

                $notice = LsankNotice::create([
                    'notice_no' =>
                        $this->generateNoticeNumber(),

                    'template_code' =>
                        'NPP',

                    'notice_type' =>
                        'NPP',

                    'category' =>
                        $compoundType,

                    'status' =>
                        'Dikeluarkan',

                    'okn_name' =>
                        $partyName,

                    'identity_no' =>
                        $registerNo,

                    'registered_address' =>
                        $this->firstValue([
                            $application->business_address ?? null,
                            $application->address ?? null,
                        ]),

                    'phone' =>
                        $this->firstValue([
                            $application->business_phone ?? null,
                            $application->phone_no ?? null,
                            $application->phone ?? null,
                            $holder->phone ?? null,
                        ]),

                    'offence_section' =>
                        $sectionRegulation,

                    'activity_category' =>
                        $compoundType,

                    'offence_details' =>
                        $description,

                    'offence_location' =>
                        $location,

                    'inspection_date' =>
                        $report->inspection_date ?? null,

                    'inspection_time' =>
                        $this->resolveInspectionTime(
                            $report->form_data ?? null
                        ),

                    'coordinates' =>
                        $this->resolveCoordinates(
                            $report->latitude ?? null,
                            $report->longitude ?? null
                        ),

                    'compound_amount' =>
                        $amount,

                    'compound_status' =>
                        'Belum Bayar',

                    'compound_due_date' =>
                        $dueAt->toDateString(),

                    'form_data' =>
                        json_encode(
                            [
                                'source' =>
                                    'compound',

                                'compound_id' =>
                                    $compound->compound_id,

                                'compound_no' =>
                                    $compound->compound_no,

                                'invoice_id' =>
                                    $invoice->invoice_id,

                                'invoice_no' =>
                                    $invoice->invoice_no,

                                'inspection_report_id' =>
                                    $inspectionReportId,

                                'report_no' =>
                                    $report->report_no ?? null,

                                'payment_required' =>
                                    true,

                                'payment_type' =>
                                    'Kompaun',

                                'due_at' =>
                                    $dueAt->toIso8601String(),
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),

                    'created_by' =>
                        $actor->user_id,

                    'updated_by' =>
                        $actor->user_id,

                    'issued_at' =>
                        $issuedAt,
                ]);

                /*
                |--------------------------------------------------------------------------
                | CREATE NOTICE RESTRICTION
                |--------------------------------------------------------------------------
                |
                | NPP cannot be resolved by typing a response.
                | It requires compound payment.
                |
                */

                LsankNoticeRestriction::create([
                    'notice_id' =>
                        $notice->notice_id,

                    'user_id' =>
                        $holder->user_id,

                    'license_id' =>
                        $license->license_id,

                    'application_id' =>
                        $application->application_id,

                    'notice_no' =>
                        $notice->notice_no,

                    'notice_type' =>
                        'NPP',

                    'category' =>
                        $compoundType,

                    'restriction_status' =>
                        'active',

                    'response_required' =>
                        'compound_payment',

                    'restriction_reason' =>
                        'Kompaun perlu dibayar dalam tempoh 14 hari.',

                    'response_data' =>
                        null,

                    'responded_at' =>
                        null,

                    'resolved_at' =>
                        null,

                    'created_by' =>
                        $actor->user_id,
                ]);

                /*
                |--------------------------------------------------------------------------
                | LINK COMPOUND WITH INVOICE + NOTICE
                |--------------------------------------------------------------------------
                */

                $compound->forceFill([
                    'invoice_id' =>
                        $invoice->invoice_id,

                    'notice_id' =>
                        $notice->notice_id,

                    'updated_by' =>
                        $actor->user_id,
                ])->save();

                return $compound->fresh([
                    'license',
                    'application',
                    'user',
                    'invoice',
                    'notice',
                ]);
            },
            3
        );

        return $compound;
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAID
    |--------------------------------------------------------------------------
    |
    | Called after a compound invoice is successfully paid.
    |
    */

    public function markPaidByInvoice(
        LsankInvoice $invoice,
        ?LsankPayment $payment = null
    ): ?LsankCompound {
        return DB::transaction(
            function () use (
                $invoice,
                $payment
            ) {
                $compound = LsankCompound::query()
                    ->where(
                        'invoice_id',
                        $invoice->invoice_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $compound) {
                    /*
                     * This invoice is not a compound invoice.
                     */
                    return null;
                }

                /*
                 * Idempotent callback protection.
                 */
                if ($compound->isPaid()) {
                    return $compound->fresh([
                        'invoice',
                        'notice',
                    ]);
                }

                $now = now();

                /*
                |--------------------------------------------------------------------------
                | UPDATE INVOICE
                |--------------------------------------------------------------------------
                */

                LsankInvoice::query()
                    ->where(
                        'invoice_id',
                        $invoice->invoice_id
                    )
                    ->update([
                        'status' =>
                            'paid',

                        'updated_at' =>
                            $now,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | UPDATE COMPOUND
                |--------------------------------------------------------------------------
                */

                $compound->forceFill([
                    'workflow_status' =>
                        LsankCompound::STATUS_PAID,

                    'paid_at' =>
                        $now,
                ])->save();

                /*
                |--------------------------------------------------------------------------
                | UPDATE NPP
                |--------------------------------------------------------------------------
                */

                if ($compound->notice_id !== null) {
                    LsankNotice::query()
                        ->where(
                            'notice_id',
                            $compound->notice_id
                        )
                        ->update([
                            'compound_status' =>
                                'Telah Bayar',

                            'status' =>
                                'Selesai',

                            'updated_at' =>
                                $now,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | RELEASE NOTICE RESTRICTION
                    |--------------------------------------------------------------------------
                    */

                    LsankNoticeRestriction::query()
                        ->where(
                            'notice_id',
                            $compound->notice_id
                        )
                        ->where(
                            'restriction_status',
                            'active'
                        )
                        ->update([
                            'restriction_status' =>
                                'resolved',

                            'responded_at' =>
                                $now,

                            'resolved_at' =>
                                $now,

                            'response_data' =>
                                json_encode(
                                    [
                                        'response_type' =>
                                            'compound_payment',

                                        'invoice_id' =>
                                            $invoice->invoice_id,

                                        'invoice_no' =>
                                            $invoice->invoice_no,

                                        'payment_id' =>
                                            $payment?->payment_id,

                                        'paid_at' =>
                                            $now->toIso8601String(),
                                    ],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                ),

                            'updated_at' =>
                                $now,
                        ]);
                }

                /*
                 * Important:
                 *
                 * If legal referral was already created before late payment,
                 * we DO NOT automatically delete/resolve the legal referral.
                 *
                 * Legal staff must close/cancel that case manually.
                 */

                return $compound->fresh([
                    'invoice',
                    'notice',
                    'legalReferral',
                ]);
            },
            3
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MARK OVERDUE
    |--------------------------------------------------------------------------
    |
    | This does NOT create legal referral yet.
    | Legal escalation will be connected in the next step.
    |
    */

    public function markOverdue(
        LsankCompound $compound
    ): LsankCompound {
        return DB::transaction(
            function () use ($compound) {
                $lockedCompound = LsankCompound::query()
                    ->where(
                        'compound_id',
                        $compound->compound_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! $lockedCompound->canBeMarkedOverdue()
                ) {
                    return $lockedCompound;
                }

                /*
                 * Double-check invoice status.
                 */
                if ($lockedCompound->invoice_id !== null) {
                    $invoice = LsankInvoice::query()
                        ->where(
                            'invoice_id',
                            $lockedCompound->invoice_id
                        )
                        ->lockForUpdate()
                        ->first();

                    if (
                        $invoice
                        && strtolower(
                            trim(
                                (string) $invoice->status
                            )
                        ) === 'paid'
                    ) {
                        return $this->markPaidByInvoice(
                            $invoice
                        );
                    }
                }

                $now = now();

                $lockedCompound->forceFill([
                    'workflow_status' =>
                        LsankCompound::STATUS_OVERDUE,

                    'overdue_at' =>
                        $now,
                ])->save();

                if (
                    $lockedCompound->notice_id !== null
                ) {
                    LsankNotice::query()
                        ->where(
                            'notice_id',
                            $lockedCompound->notice_id
                        )
                        ->update([
                            'compound_status' =>
                                'Tamat 14 Hari',

                            'status' =>
                                'Tamat Tempoh 14 Hari',

                            'updated_at' =>
                                $now,
                        ]);

                    /*
                     * Restriction intentionally remains ACTIVE.
                     *
                     * User has still not paid.
                     */
                }

                return $lockedCompound->fresh([
                    'invoice',
                    'notice',
                ]);
            },
            3
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NUMBER GENERATORS
    |--------------------------------------------------------------------------
    */

    private function generateCompoundNumber(): string
    {
        return $this->generateSequentialNumber(
            table: 'lsank_compounds',
            column: 'compound_no',
            prefix: 'KMP-' . now()->year . '-',
            digits: 6
        );
    }

    private function generateInvoiceNumber(): string
    {
        return $this->generateSequentialNumber(
            table: 'lsank_invoices',
            column: 'invoice_no',
            prefix: 'INV-KMP-' . now()->year . '-',
            digits: 6
        );
    }

    private function generateNoticeNumber(): string
    {
        return $this->generateSequentialNumber(
            table: 'lsank_notices',
            column: 'notice_no',
            prefix: 'NPP-' . now()->year . '-',
            digits: 6
        );
    }

    private function generateSequentialNumber(
        string $table,
        string $column,
        string $prefix,
        int $digits
    ): string {
        $latest = DB::table($table)
            ->where(
                $column,
                'like',
                $prefix . '%'
            )
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $next = 1;

        if (
            is_string($latest)
            && preg_match(
                '/(\d+)$/',
                $latest,
                $matches
            )
        ) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix
                . str_pad(
                    (string) $next,
                    $digits,
                    '0',
                    STR_PAD_LEFT
                );

            $exists = DB::table($table)
                ->where(
                    $column,
                    $candidate
                )
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $next++;
        } while (true);
    }

    /*
    |--------------------------------------------------------------------------
    | DATA HELPERS
    |--------------------------------------------------------------------------
    */

    private function normalizeAmount(
        mixed $value
    ): float {
        if ($value === null) {
            return 0.0;
        }

        $cleaned = str_replace(
            [
                'RM',
                'rm',
                ',',
                ' ',
            ],
            '',
            (string) $value
        );

        return round(
            (float) $cleaned,
            2
        );
    }

    private function firstValue(
        array $values,
        ?string $fallback = null
    ): ?string {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $text = trim(
                (string) $value
            );

            if ($text === '') {
                continue;
            }

            $normalized = strtoupper(
                $text
            );

            if (
                $normalized === 'NULL'
                || $normalized === 'N/A'
                || $text === '-'
            ) {
                continue;
            }

            return $text;
        }

        return $fallback;
    }

    private function resolveCoordinates(
        mixed $latitude,
        mixed $longitude
    ): ?string {
        if (
            $latitude === null
            || $longitude === null
        ) {
            return null;
        }

        $lat = trim(
            (string) $latitude
        );

        $lng = trim(
            (string) $longitude
        );

        if (
            $lat === ''
            || $lng === ''
        ) {
            return null;
        }

        return $lat . ',' . $lng;
    }

    private function resolveInspectionTime(
        mixed $rawFormData
    ): ?string {
        if ($rawFormData === null) {
            return null;
        }

        $formData = null;

        if (is_array($rawFormData)) {
            $formData = $rawFormData;
        }

        if (
            is_string($rawFormData)
            && trim($rawFormData) !== ''
        ) {
            $decoded = json_decode(
                $rawFormData,
                true
            );

            if (is_array($decoded)) {
                $formData = $decoded;
            }
        }

        if (! is_array($formData)) {
            return null;
        }

        foreach (
            [
                'inspection_time',
                'inspectionTime',
                'masa_pemeriksaan',
                'masaPemeriksaan',
                'time',
            ] as $key
        ) {
            if (
                isset($formData[$key])
                && trim(
                    (string) $formData[$key]
                ) !== ''
            ) {
                return trim(
                    (string) $formData[$key]
                );
            }
        }

        return null;
    }
}