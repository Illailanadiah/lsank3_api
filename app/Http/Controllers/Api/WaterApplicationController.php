<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesApplicationData;
use App\Http\Controllers\Controller;
use App\Models\LsankApplicant;
use App\Models\LsankApplication;
use App\Models\LsankCompany;
use App\Models\LsankInvoice;
use App\Models\LsankPayment;
use App\Models\LsankReceipt;
use App\Models\LsankWaterBodyApplication;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WaterApplicationController extends Controller
{
    use HandlesApplicationData;

    private const TYPE_CODE = 'WATER';
    private const TYPE_NAME = 'Aktiviti Badan Perairan';
    private const PROCESSING_PAYMENT_TYPE = 'Fi Pemprosesan';
    private const FINAL_PAYMENT_TYPES = [
        'Fi Lesen',
        'Fi Caj',
        'Wang Sekuriti',
    ];

    public function index(Request $request)
    {
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        $applications = LsankApplication::query()
            ->with([
                'applicant',
                'applicant.company',
                'status',
                'type',
                'waterBody',
                'license.status',
                'license.terminationRequest',
            ])
            ->where('user_id', $request->user()->user_id)
            ->where('application_type_id', $typeId)
            ->latest('application_id')
            ->get()
            ->map(fn(LsankApplication $application) => $this->formatWaterListItem($application))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    public function generateInvoice(Request $request, LsankApplication $application)
    {
        $this->guardOwnedWaterApplication($request, $application);

        if (in_array($application->application_status, [
            LsankApplication::STATUS_DALAM_PROSES,
            LsankApplication::STATUS_LULUS,
            LsankApplication::STATUS_GAGAL,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini telah dihantar atau telah selesai diproses.',
            ], 422);
        }

        return DB::transaction(function () use ($application) {
            $lockedApplication = LsankApplication::query()
                ->where('application_id', $application->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            $draftData = is_array($lockedApplication->draft_data)
                ? $lockedApplication->draft_data
                : [];

            $selectedActivities = $this->resolveSelectedActivities(
                $lockedApplication,
                $draftData
            );

            if (empty($selectedActivities)) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Tiada aktiviti dipilih untuk penjanaan invois.',
                ], 422);
            }

            $splitBatchId = $draftData['split_batch_id']
                ?? 'WATER-BATCH-'
                . $lockedApplication->application_id
                . '-'
                . now()->format('YmdHis');

            $draftData['selected_activities'] = $selectedActivities;
            $draftData['processing_invoice_activities'] = $selectedActivities;
            $draftData['original_selected_activities'] = $selectedActivities;
            $draftData['split_batch_id'] = $splitBatchId;
            $draftData['is_split_parent'] = true;
            $draftData['is_split_child'] = false;

            $lockedApplication->draft_data = $draftData;
            $lockedApplication->application_status_id = $this->applicationStatusId(
                'payment',
                'Fi Pemprosesan',
                2
            );
            $lockedApplication->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
            $lockedApplication->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
            $lockedApplication->submitted_at = null;
            $lockedApplication->save();

            LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
                ->where('status', 'unpaid')
                ->delete();

            $startRunningNumber = $this->nextInvoiceRunningNumber();
            $createdInvoices = collect();

            foreach ($selectedActivities as $index => $activity) {
                $createdInvoices->push(
                    LsankInvoice::create([
                        'application_id' => $lockedApplication->application_id,
                        'user_id' => $lockedApplication->user_id,
                        'invoice_no' => $this->generateInvoiceNoByRunningNumber(
                            $startRunningNumber + $index,
                            '01'
                        ),
                        'payment_type' => self::PROCESSING_PAYMENT_TYPE,
                        'invoice_date' => now()->toDateString(),
                        'due_date' => now()->addDays(14)->toDateString(),
                        'total_amount' => 150,
                        'status' => 'unpaid',
                    ])
                );
            }

            $firstInvoice = $createdInvoices->first();

            return response()->json([
                'success' => true,
                'message' => $createdInvoices->count() > 1
                    ? 'Invois fi pemprosesan berjaya dijana mengikut jenis aktiviti.'
                    : 'Invois fi pemprosesan berjaya dijana.',
                'data' => [
                    'id' => $lockedApplication->application_id,
                    'application_id' => $lockedApplication->application_id,
                    'application_ids' => [$lockedApplication->application_id],
                    'application_no' => $lockedApplication->application_ref_no,
                    'application_ref_no' => $lockedApplication->application_ref_no,
                    'application_nos' => [$lockedApplication->application_ref_no],
                    'application_ref_nos' => [$lockedApplication->application_ref_no],
                    'activity_names' => $selectedActivities,
                    'invoice_id' => $firstInvoice?->invoice_id,
                    'invoice_ids' => $createdInvoices->pluck('invoice_id')->values()->all(),
                    'invoice_no' => $firstInvoice?->invoice_no,
                    'invoice_nos' => $createdInvoices->pluck('invoice_no')->values()->all(),
                    'processing_fee' => $createdInvoices->count() * 150,
                    'processing_fee_display' => 'RM '
                        . number_format($createdInvoices->count() * 150, 2),
                    'split_batch_id' => $splitBatchId,
                    'status' => LsankApplication::STATUS_FI_PEMPROSESAN,
                    'payment_status' => LsankApplication::PAYMENT_MENUNGGU_BAYARAN,
                ],
            ]);
        });
    }

    public function payInvoice(
        Request $request,
        LsankApplication $application,
        LsankInvoice $invoice
    ) {
        $this->guardOwnedWaterApplication($request, $application);
        $this->guardInvoiceOwnership($request, $application, $invoice);

        if (trim((string) ($invoice->payment_type ?? self::PROCESSING_PAYMENT_TYPE)) !== self::PROCESSING_PAYMENT_TYPE) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini bukan invois Fi Pemprosesan.',
            ], 422);
        }

        if ($application->application_status !== LsankApplication::STATUS_FI_PEMPROSESAN) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini bukan lagi di peringkat Fi Pemprosesan.',
            ], 422);
        }

        return DB::transaction(function () use ($application, $invoice) {
            $lockedApplication = LsankApplication::query()
                ->where('application_id', $application->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice = LsankInvoice::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->where('application_id', $lockedApplication->application_id)
                ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->isPaidInvoice($lockedInvoice)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invois ini telah dibayar.',
                ], 422);
            }

            $existingReceipt = LsankReceipt::query()
                ->where('invoice_id', $lockedInvoice->invoice_id)
                ->lockForUpdate()
                ->first();

            if ($existingReceipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bayaran bagi invois ini telah direkodkan.',
                ], 422);
            }

            $draftData = is_array($lockedApplication->draft_data)
                ? $lockedApplication->draft_data
                : [];

            $selectedActivities = collect(
                $this->resolveSelectedActivities($lockedApplication, $draftData)
            );

            $unpaidInvoices = LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
                ->where('status', 'unpaid')
                ->orderBy('invoice_id')
                ->lockForUpdate()
                ->get()
                ->values();

            $invoiceIndex = $unpaidInvoices->search(
                fn(LsankInvoice $item) =>
                (int) $item->invoice_id === (int) $lockedInvoice->invoice_id
            );

            if ($invoiceIndex === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invois belum bayar tidak dijumpai.',
                ], 422);
            }

            $selectedActivity = $selectedActivities->get($invoiceIndex)
                ?? $selectedActivities->first()
                ?? $lockedApplication->activity_name
                ?? 'Aktiviti Rekreasi Sukan Air';

            $remainingActivities = $selectedActivities
                ->reject(fn($item, $index) => (int) $index === (int) $invoiceIndex)
                ->values();

            $splitBatchId = $draftData['split_batch_id']
                ?? 'WATER-BATCH-'
                . $lockedApplication->application_id
                . '-'
                . now()->format('YmdHis');

            $paidApplication = $this->createPaidSplitApplication(
                $lockedApplication,
                $draftData,
                $selectedActivity,
                $remainingActivities,
                $splitBatchId
            );

            $lockedInvoice->application_id = $paidApplication->application_id;
            $lockedInvoice->user_id = $paidApplication->user_id;
            $lockedInvoice->status = 'paid';
            $lockedInvoice->save();

            $payment = $this->createPayment(
                $lockedInvoice,
                'TEST-WATER-SINGLE-'
                    . $paidApplication->application_id
                    . '-'
                    . $lockedInvoice->invoice_id
            );

            $receipt = $this->createReceipt($lockedInvoice, $payment, '01');

            $remainingCount = LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
                ->where('status', 'unpaid')
                ->count();

            return response()->json([
                'success' => true,
                'message' => $remainingCount > 0
                    ? 'Bayaran satu invois berjaya. Masih terdapat invois aktiviti yang belum dibayar.'
                    : 'Bayaran invois berjaya. Permohonan telah dihantar untuk semakan.',
                'data' => [
                    'id' => $paidApplication->application_id,
                    'application_id' => $paidApplication->application_id,
                    'application_ids' => [$paidApplication->application_id],
                    'application_no' => $paidApplication->application_ref_no,
                    'application_ref_no' => $paidApplication->application_ref_no,
                    'application_nos' => [$paidApplication->application_ref_no],
                    'application_ref_nos' => [$paidApplication->application_ref_no],
                    'activity_name' => $selectedActivity,
                    'activity_names' => [$selectedActivity],
                    'invoice_id' => $lockedInvoice->invoice_id,
                    'invoice_ids' => [$lockedInvoice->invoice_id],
                    'invoice_no' => $lockedInvoice->invoice_no,
                    'invoice_nos' => [$lockedInvoice->invoice_no],
                    'payment_type' => self::PROCESSING_PAYMENT_TYPE,
                    'payment_id' => $payment->payment_id,
                    'payment_ids' => [$payment->payment_id],
                    'receipt_id' => $receipt->receipt_id,
                    'receipt_ids' => [$receipt->receipt_id],
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_nos' => [$receipt->receipt_no],
                    'processing_fee' => (float) $lockedInvoice->total_amount,
                    'processing_fee_display' => 'RM '
                        . number_format($lockedInvoice->total_amount, 2),
                    'amount' => (float) $lockedInvoice->total_amount,
                    'total_amount' => (float) $lockedInvoice->total_amount,
                    'split_batch_id' => $splitBatchId,
                    'remaining_unpaid_invoice_count' => $remainingCount,
                    'has_remaining_unpaid_invoice' => $remainingCount > 0,
                    'status' => LsankApplication::STATUS_DALAM_PROSES,
                    'status_display' => 'Dalam Proses',
                    'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                    'payment_status_display' => 'Sudah Bayar',
                    'paid_at' => now()->toDateTimeString(),
                    'payment_date' => now()->toDateTimeString(),
                ],
            ]);
        });
    }

    public function payFinalInvoice(
        Request $request,
        LsankApplication $application,
        LsankInvoice $invoice,
        LicenseService $licenseService
    ) {
        $this->guardOwnedWaterApplication($request, $application);
        $this->guardInvoiceOwnership($request, $application, $invoice);

        $paymentType = trim((string) $invoice->payment_type);

        if (!in_array($paymentType, self::FINAL_PAYMENT_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini bukan invois bayaran akhir.',
            ], 422);
        }

        if (!$application->isDirectorApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan belum diluluskan oleh Ketua Pengarah.',
            ], 422);
        }

        return DB::transaction(function () use (
            $application,
            $invoice,
            $licenseService
        ) {
            $lockedApplication = LsankApplication::query()
                ->where('application_id', $application->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedApplication->isDirectorApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan belum diluluskan oleh Ketua Pengarah.',
                ], 422);
            }

            $lockedInvoice = LsankInvoice::query()
                ->where('invoice_id', $invoice->invoice_id)
                ->where('application_id', $lockedApplication->application_id)
                ->whereIn('payment_type', self::FINAL_PAYMENT_TYPES)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->isPaidInvoice($lockedInvoice)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invois ini telah dibayar.',
                ], 422);
            }

            $existingReceipt = LsankReceipt::query()
                ->where('invoice_id', $lockedInvoice->invoice_id)
                ->lockForUpdate()
                ->first();

            if ($existingReceipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bayaran bagi invois ini telah direkodkan.',
                ], 422);
            }

            $existingTypes = LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->whereIn('payment_type', self::FINAL_PAYMENT_TYPES)
                ->pluck('payment_type')
                ->map(fn($type) => trim((string) $type))
                ->unique()
                ->values();

            $missingTypes = collect(self::FINAL_PAYMENT_TYPES)->diff($existingTypes);

            if ($missingTypes->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invois bayaran akhir tidak lengkap.',
                    'data' => [
                        'missing_payment_types' => $missingTypes->values()->all(),
                    ],
                ], 422);
            }

            $lockedInvoice->status = 'paid';
            $lockedInvoice->save();

            $payment = $this->createPayment(
                $lockedInvoice,
                'TEST-WATER-FINAL-SINGLE-'
                    . $lockedApplication->application_id
                    . '-'
                    . $lockedInvoice->invoice_id
            );

            $feeCode = match (trim((string) $lockedInvoice->payment_type)) {
                'Fi Lesen' => '02',
                'Fi Caj' => '03',
                'Wang Sekuriti' => '04',
                default => '00',
            };

            $receipt = $this->createReceipt(
                $lockedInvoice,
                $payment,
                $feeCode
            );

            $remainingInvoices = LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->whereIn('payment_type', self::FINAL_PAYMENT_TYPES)
                ->where('status', '!=', 'paid')
                ->orderBy('invoice_id')
                ->get();

            if ($remainingInvoices->isNotEmpty()) {
                $reviewData = is_array($lockedApplication->review_data)
                    ? $lockedApplication->review_data
                    : [];

                $reviewData['final_invoice_status'] = 'pending_payment';

                $lockedApplication->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
                $lockedApplication->review_data = $reviewData;
                $lockedApplication->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Bayaran invois berjaya. Masih terdapat invois yang belum dibayar.',
                    'data' => [
                        'id' => $lockedApplication->application_id,
                        'application_id' => $lockedApplication->application_id,
                        'application_ids' => [$lockedApplication->application_id],
                        'application_no' => $lockedApplication->application_ref_no,
                        'application_ref_no' => $lockedApplication->application_ref_no,
                        'application_nos' => [$lockedApplication->application_ref_no],
                        'application_ref_nos' => [$lockedApplication->application_ref_no],
                        'invoice_id' => $lockedInvoice->invoice_id,
                        'invoice_ids' => [$lockedInvoice->invoice_id],
                        'invoice_no' => $lockedInvoice->invoice_no,
                        'invoice_nos' => [$lockedInvoice->invoice_no],
                        'payment_type' => $lockedInvoice->payment_type,
                        'payment_id' => $payment->payment_id,
                        'payment_ids' => [$payment->payment_id],
                        'receipt_id' => $receipt->receipt_id,
                        'receipt_ids' => [$receipt->receipt_id],
                        'receipt_no' => $receipt->receipt_no,
                        'receipt_nos' => [$receipt->receipt_no],
                        'amount' => (float) $lockedInvoice->total_amount,
                        'total_amount' => (float) $lockedInvoice->total_amount,
                        'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                        'payment_status_display' => 'Sudah Bayar',
                        'invoice_payment_status' => 'paid',
                        'invoice_payment_status_display' => 'Sudah Bayar',
                        'application_payment_status' => $lockedApplication->payment_status,
                        'application_payment_status_display' => 'Menunggu Bayaran',
                        'application_status' => $lockedApplication->application_status,
                        'application_status_display' => 'Lulus - Menunggu Bayaran Lengkap',
                        'status_display' => 'Lulus - Menunggu Bayaran Lengkap',
                        'paid_at' => now()->toDateTimeString(),
                        'payment_date' => now()->toDateTimeString(),
                        'remaining_unpaid_invoice_count' => $remainingInvoices->count(),
                        'remaining_invoices' => $remainingInvoices
                            ->map(fn(LsankInvoice $item) => [
                                'invoice_id' => $item->invoice_id,
                                'invoice_no' => $item->invoice_no,
                                'payment_type' => $item->payment_type,
                                'amount' => (float) $item->total_amount,
                                'status' => $item->status,
                            ])
                            ->values()
                            ->all(),
                        'all_final_invoices_paid' => false,
                        'license_generated' => false,
                    ],
                ]);
            }

            $reviewData = is_array($lockedApplication->review_data)
                ? $lockedApplication->review_data
                : [];

            $reviewData['final_invoice_status'] = 'paid';
            $reviewData['final_paid_at'] = now()->toDateTimeString();

            $lockedApplication->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
            $lockedApplication->review_data = $reviewData;
            $lockedApplication->save();

            $license = $licenseService->generateForApprovedApplication(
                $lockedApplication->fresh()
            );

            LsankInvoice::query()
                ->where('application_id', $lockedApplication->application_id)
                ->whereIn('payment_type', self::FINAL_PAYMENT_TYPES)
                ->update([
                    'license_id' => $license->license_id,
                ]);

            $freshApplication = $lockedApplication->fresh();
            $latestReviewData = is_array($freshApplication->review_data)
                ? $freshApplication->review_data
                : [];

            $latestReviewData['license_generation_status'] = 'generated';
            $latestReviewData['license_id'] = $license->license_id;
            $latestReviewData['license_no'] = $license->license_no;
            $latestReviewData['license_generated_at'] = now()->toDateTimeString();

            $freshApplication->review_data = $latestReviewData;
            $freshApplication->save();

            return response()->json([
                'success' => true,
                'message' => 'Semua invois telah dibayar. Lesen berjaya dijana.',
                'data' => [
                    'id' => $freshApplication->application_id,
                    'application_id' => $freshApplication->application_id,
                    'application_ids' => [$freshApplication->application_id],
                    'application_no' => $freshApplication->application_ref_no,
                    'application_ref_no' => $freshApplication->application_ref_no,
                    'application_nos' => [$freshApplication->application_ref_no],
                    'application_ref_nos' => [$freshApplication->application_ref_no],
                    'invoice_id' => $lockedInvoice->invoice_id,
                    'invoice_ids' => [$lockedInvoice->invoice_id],
                    'invoice_no' => $lockedInvoice->invoice_no,
                    'invoice_nos' => [$lockedInvoice->invoice_no],
                    'payment_type' => $lockedInvoice->payment_type,
                    'payment_id' => $payment->payment_id,
                    'payment_ids' => [$payment->payment_id],
                    'receipt_id' => $receipt->receipt_id,
                    'receipt_ids' => [$receipt->receipt_id],
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_nos' => [$receipt->receipt_no],
                    'amount' => (float) $lockedInvoice->total_amount,
                    'total_amount' => (float) $lockedInvoice->total_amount,
                    'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                    'payment_status_display' => 'Sudah Bayar',
                    'application_payment_status' => $freshApplication->payment_status,
                    'application_payment_status_display' => 'Sudah Bayar',
                    'application_status' => $freshApplication->application_status,
                    'application_status_display' => 'Lesen Aktif',
                    'status_display' => 'Lesen Aktif',
                    'paid_at' => now()->toDateTimeString(),
                    'payment_date' => now()->toDateTimeString(),
                    'remaining_unpaid_invoice_count' => 0,
                    'all_final_invoices_paid' => true,
                    'license_generated' => true,
                    'license_id' => $license->license_id,
                    'license_no' => $license->license_no,
                    'license_start_date' => optional($license->start_date)->format('Y-m-d'),
                    'license_expiry_date' => optional($license->expiry_date)->format('Y-m-d'),
                    'license_status' => $license->display_status,
                ],
            ]);
        });
    }

    public function show(Request $request, LsankApplication $application)
    {
        $this->guardOwnedWaterApplication($request, $application);

        $application->load([
            'user',
            'applicant.company',
            'status',
            'type',
            'waterBody',
            'documents',
            'reviews',
            'license.status',
            'license.terminationRequest',
        ]);

        $detail = $this->formatApplicationDetail($application, self::TYPE_NAME);
        $draftData = is_array($application->draft_data)
            ? $application->draft_data
            : [];

        $applicationIds = $this->resolveBatchApplicationIds(
            $application,
            $draftData
        );

        $applicationRefs = $this->resolveBatchApplicationRefs(
            $application,
            $draftData
        );

        $detail = array_merge($detail, [
            'id' => $application->application_id,
            'application_id' => $application->application_id,
            'application_no' => $application->application_ref_no,
            'application_ref_no' => $application->application_ref_no,
            'current_step' => $application->current_step ?? 0,
            'draft_data' => $application->draft_data ?? [],
            'review_data' => $application->review_data ?? [],
            'submitted_data' => $application->submitted_data ?? [],
            'applicant_type' => $application->applicant_type,
            'applicant_name' => $application->applicant_name,
            'identity_no' => $application->identity_no,
            'email' => $application->email,
            'phone_no' => $application->phone_no,
            'phone' => $application->phone,
            'address' => $application->address,
            'company_name' => $application->company_name,
            'business_name' => $application->business_name,
            'registration_no' => $application->registration_no,
            'business_address' => $application->business_address,
            'business_phone' => $application->business_phone,
            'business_email' => $application->business_email,
            'responsible_officer_name' => $application->responsible_officer_name,
            'responsible_officer_phone' => $application->responsible_officer_phone,
            'responsible_officer_position' => $application->responsible_officer_position,
            'officers' => $application->officers ?? [],
            'activity_name' => $application->activity_name,
            'activity_details' => $application->activity_details,
            'district' => $application->district,
            'activity_location' => $application->activity_location,
            'longitude' => $application->longitude,
            'latitude' => $application->latitude,
            'operating_days' => $application->operating_days,
            'operating_time' => $application->operating_time,
            'recreation_details' => $application->recreation_details ?? [],
            'invoice_items' => $this->formatInvoiceItems($applicationIds),
            'receipt_items' => $this->formatReceiptItems($applicationIds),
            'processing_invoice_activities' => $draftData['processing_invoice_activities']
                ?? $draftData['original_selected_activities']
                ?? $draftData['selected_activities']
                ?? [$application->activity_name ?? $application->activity_details ?? '-'],
            'application_ref_nos' => $applicationRefs,
            'application_nos' => $applicationRefs,
        ]);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function destroyDraft(Request $request, LsankApplication $application)
    {
        $this->guardOwnedWaterApplication($request, $application);

        if (!in_array($application->application_status, [
            LsankApplication::STATUS_DRAF,
            LsankApplication::STATUS_FI_PEMPROSESAN,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permohonan berstatus draf atau fi pemprosesan sahaja boleh dipadam.',
            ], 422);
        }

        if ($application->payment_status === LsankApplication::PAYMENT_SUDAH_BAYAR) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini tidak boleh dipadam kerana bayaran telah berjaya dibuat.',
            ], 422);
        }

        return DB::transaction(function () use ($application) {
            LsankInvoice::query()
                ->where('application_id', $application->application_id)
                ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
                ->where('status', 'unpaid')
                ->delete();

            $application->documents()->delete();
            $application->waterBody()->delete();
            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan berjaya dipadam.',
            ]);
        });
    }

    public function saveDraft(Request $request)
    {
        return $this->saveApplicationRecord($request, true);
    }

    public function store(Request $request)
    {
        return $this->saveApplicationRecord($request, false);
    }

    public function pay(Request $request, LsankApplication $application)
    {
        $this->guardOwnedWaterApplication($request, $application);

        if ($application->application_status !== LsankApplication::STATUS_FI_PEMPROSESAN) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini belum berada di peringkat Fi Pemprosesan.',
            ], 422);
        }

        $unpaidInvoiceIds = LsankInvoice::query()
            ->where('application_id', $application->application_id)
            ->where('payment_type', self::PROCESSING_PAYMENT_TYPE)
            ->where('status', 'unpaid')
            ->orderBy('invoice_id')
            ->pluck('invoice_id')
            ->values()
            ->all();

        if (empty($unpaidInvoiceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tiada invois Fi Pemprosesan yang belum dibayar.',
            ], 422);
        }

        $paidInvoices = [];
        $paidReceipts = [];
        $paidApplications = [];
        $totalPaid = 0.0;
        $lastResponseData = [];

        foreach ($unpaidInvoiceIds as $invoiceId) {
            $freshApplication = LsankApplication::query()
                ->where('application_id', $application->application_id)
                ->firstOrFail();

            $invoice = LsankInvoice::query()
                ->where('invoice_id', $invoiceId)
                ->firstOrFail();

            $response = $this->payInvoice(
                $request,
                $freshApplication,
                $invoice
            );

            $payload = $response->getData(true);

            if ($response->getStatusCode() >= 400 || !($payload['success'] ?? false)) {
                return $response;
            }

            $data = is_array($payload['data'] ?? null)
                ? $payload['data']
                : [];

            $lastResponseData = $data;
            $totalPaid += (float) ($data['amount'] ?? $data['total_amount'] ?? 0);

            if (!empty($data['invoice_id'])) {
                $paidInvoices[] = [
                    'invoice_id' => $data['invoice_id'],
                    'invoice_no' => $data['invoice_no'] ?? null,
                    'activity_name' => $data['activity_name'] ?? null,
                    'amount' => (float) ($data['amount'] ?? 0),
                ];
            }

            if (!empty($data['receipt_id'])) {
                $paidReceipts[] = [
                    'receipt_id' => $data['receipt_id'],
                    'receipt_no' => $data['receipt_no'] ?? null,
                ];
            }

            if (!empty($data['application_id'])) {
                $paidApplications[] = [
                    'application_id' => $data['application_id'],
                    'application_ref_no' => $data['application_ref_no'] ?? null,
                    'activity_name' => $data['activity_name'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($paidInvoices) > 1
                ? 'Semua invois Fi Pemprosesan berjaya dibayar.'
                : 'Invois Fi Pemprosesan berjaya dibayar.',
            'data' => array_merge($lastResponseData, [
                'paid_all' => true,
                'paid_invoice_count' => count($paidInvoices),
                'invoice_ids' => array_values(array_filter(array_column($paidInvoices, 'invoice_id'))),
                'invoice_nos' => array_values(array_filter(array_column($paidInvoices, 'invoice_no'))),
                'receipt_ids' => array_values(array_filter(array_column($paidReceipts, 'receipt_id'))),
                'receipt_nos' => array_values(array_filter(array_column($paidReceipts, 'receipt_no'))),
                'application_ids' => array_values(array_filter(array_column($paidApplications, 'application_id'))),
                'application_ref_nos' => array_values(array_filter(array_column($paidApplications, 'application_ref_no'))),
                'paid_invoices' => $paidInvoices,
                'paid_receipts' => $paidReceipts,
                'paid_applications' => $paidApplications,
                'total_paid' => $totalPaid,
                'total_paid_display' => 'RM ' . number_format($totalPaid, 2),
                'remaining_unpaid_invoice_count' => 0,
                'has_remaining_unpaid_invoice' => false,
            ]),
        ]);
    }

    private function saveApplicationRecord(Request $request, bool $allowExisting)
    {
        $validated = $request->validate([
            'application_id' => ['nullable', 'integer'],
            'applicant_type' => ['nullable', 'string', 'max:100'],
            'applicant_name' => [$allowExisting ? 'nullable' : 'required', 'string', 'max:255'],
            'identity_no' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_no' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'business_address' => ['nullable', 'string'],
            'business_phone' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'responsible_officer_name' => ['nullable', 'string', 'max:255'],
            'responsible_officer_phone' => ['nullable', 'string', 'max:30'],
            'responsible_officer_position' => ['nullable', 'string', 'max:255'],
            'officers' => ['nullable', 'array'],
            'activity_type_id' => ['nullable', 'integer'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'activity_location' => ['nullable', 'string'],
            'longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'operating_days' => ['nullable', 'string', 'max:255'],
            'operating_time' => ['nullable', 'string', 'max:255'],
            'motorized_fee' => ['nullable', 'numeric'],
            'non_motorized_fee' => ['nullable', 'numeric'],
            'activity_details' => ['nullable', 'string'],
            'recreation_details' => ['nullable', 'array'],
            'current_step' => ['nullable', 'integer'],
            'draft_data' => ['nullable', 'array'],
            'submitted_data' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $validated, $user, $allowExisting) {
            $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
            $draftStatusId = $this->applicationStatusId('draft', 'Draf', 1);
            $application = null;

            if ($allowExisting && !empty($validated['application_id'])) {
                $application = LsankApplication::query()
                    ->where('application_id', $validated['application_id'])
                    ->where('user_id', $user->user_id)
                    ->where('application_type_id', $typeId)
                    ->lockForUpdate()
                    ->first();
            }

            if ($application && !in_array($application->application_status, [
                LsankApplication::STATUS_DRAF,
                LsankApplication::STATUS_FI_PEMPROSESAN,
            ], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan ini tidak boleh dikemaskini kerana telah dihantar untuk semakan.',
                ], 422);
            }

            $applicant = $application
                ? LsankApplicant::find($application->applicant_id)
                : null;

            if (!$applicant) {
                $applicant = new LsankApplicant();
                $applicant->user_id = $user->user_id;
                $applicant->status = 'active';
            }

            $phoneColumn = $this->applicantPhoneColumn();
            $applicant->applicant_type = $this->normalizeApplicantType($validated['applicant_type'] ?? null);
            $applicant->applicant_name = $validated['applicant_name'] ?? '-';
            $applicant->identity_no = $validated['identity_no'] ?? null;
            $applicant->email = $validated['email'] ?? null;
            $applicant->address = $validated['address'] ?? null;
            $applicant->{$phoneColumn} = $validated['phone_no'] ?? $validated['phone'] ?? null;
            $applicant->save();

            if (!empty($validated['company_name'])) {
                $company = LsankCompany::firstOrNew([
                    'applicant_id' => $applicant->applicant_id,
                ]);
                $company->company_name = $validated['company_name'];
                $company->registration_no = $validated['registration_no'] ?? null;
                $company->business_address = $validated['business_address'] ?? null;
                $company->business_phone = $validated['business_phone'] ?? null;
                $company->business_email = $validated['business_email'] ?? null;
                $company->responsible_officer_name = $validated['responsible_officer_name'] ?? null;
                $company->responsible_officer_phone = $validated['responsible_officer_phone'] ?? null;
                $company->save();
            }

            if (!$application) {
                $application = new LsankApplication();
                $application->application_ref_no = $allowExisting
                    ? $this->generateDraftReferenceNo($user->user_id)
                    : $this->generateApplicationFileNo(
                        $this->waterSectionCode($validated['activity_name'] ?? null),
                        $this->districtCode($validated['district'] ?? null)
                    );
                $application->user_id = $user->user_id;
                $application->application_type_id = $typeId;
                $application->application_category = 'new';
            }

            $application->applicant_id = $applicant->applicant_id;

            if ($application->exists && $application->application_status === LsankApplication::STATUS_FI_PEMPROSESAN) {
                $application->application_status_id = $this->applicationStatusId('payment', 'Fi Pemprosesan', 2);
                $application->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
                $application->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
            } else {
                $application->application_status_id = $draftStatusId;
                $application->application_status = LsankApplication::STATUS_DRAF;
                $application->payment_status = LsankApplication::PAYMENT_BELUM_BAYAR;
            }

            $this->fillApplicationFields($application, $validated, $request);
            $application->save();
            $this->saveWaterBody($application, $validated);

            return response()->json([
                'success' => true,
                'message' => $allowExisting
                    ? 'Draf permohonan badan perairan berjaya disimpan.'
                    : 'Permohonan badan perairan berjaya dihantar.',
                'data' => [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,
                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,
                    'status' => $application->application_status,
                    'payment_status' => $application->payment_status,
                    'current_step' => $application->current_step,
                ],
            ], $allowExisting ? 200 : 201);
        });
    }

    private function formatWaterListItem(LsankApplication $application): array
    {
        $draftData = is_array($application->draft_data) ? $application->draft_data : [];
        $applicationIds = $this->resolveBatchApplicationIds($application, $draftData);
        $applicationRefs = $this->resolveBatchApplicationRefs($application, $draftData);

        return [
            'id' => $application->application_id,
            'application_id' => $application->application_id,
            'application_no' => $application->application_ref_no,
            'application_ref_no' => $application->application_ref_no,
            'application_ref_nos' => $applicationRefs,
            'application_nos' => $applicationRefs,
            'processing_invoice_activities' => $draftData['processing_invoice_activities']
                ?? $draftData['original_selected_activities']
                ?? $draftData['selected_activities']
                ?? [$application->activity_name ?? $application->activity_details ?? '-'],
            'applicant_name' => $application->applicant_name ?? optional($application->applicant)->applicant_name ?? '-',
            'business_name' => $application->business_name ?? $application->company_name ?? optional(optional($application->applicant)->company)->company_name ?? '-',
            'phone' => $application->phone ?? $application->phone_no ?? '-',
            'email' => $application->email ?? '-',
            'license_type' => self::TYPE_NAME,
            'activity_type' => $application->activity_type ?? $application->activity_name ?? optional($application->waterBody)->activity_details ?? '-',
            'activity_name' => $application->activity_name ?? $application->activity_type ?? optional($application->waterBody)->activity_details ?? '-',
            'activity_details' => $application->activity_details ?? optional($application->waterBody)->activity_details ?? $application->activity_name ?? '-',
            'activity_location' => $application->activity_location ?? optional($application->waterBody)->activity_location ?? '-',
            'district' => $application->district ?? '-',
            'status_code' => $application->application_status,
            'status' => $this->displayApplicationStatus($application->application_status),
            'application_status' => $application->application_status,
            'application_status_display' => $this->displayApplicationStatus($application->application_status),
            'payment_status' => $application->payment_status,
            'payment_status_display' => $this->displayPaymentStatus($application->payment_status),
            'current_step' => $application->current_step ?? 0,
            'draft_data' => $application->draft_data,
            'submitted_at' => optional($application->submitted_at)->toDateTimeString(),
            'submitted_date' => optional($application->submitted_at ?? $application->created_at)->format('d M Y') ?? '-',
            'sort_date' => optional($application->submitted_at ?? $application->created_at)->toIso8601String(),
            'created_at' => optional($application->created_at)->toDateTimeString(),
            'updated_at' => optional($application->updated_at)->toDateTimeString(),
            'license' => $this->formatLicense($application),
            'fees' => $this->calculateWaterFees($application),
            'invoice_items' => $this->formatInvoiceItems($applicationIds),
            'receipt_items' => $this->formatReceiptItems($applicationIds),
        ];
    }

    private function formatInvoiceItems(
        array $applicationIds
    ): array {
        $invoices = LsankInvoice::query()
            ->with('application')
            ->whereIn(
                'application_id',
                $applicationIds
            )
            ->orderBy('application_id')
            ->orderBy('invoice_id')
            ->get();

        $activityCounters = [];

        return $invoices
            ->map(function (
                LsankInvoice $invoice
            ) use (&$activityCounters) {
                $application = $invoice->application;

                $applicationId =
                    (int) $invoice->application_id;

                $currentIndex =
                    $activityCounters[$applicationId] ?? 0;

                $draftData = (
                    $application &&
                    is_array($application->draft_data)
                )
                    ? $application->draft_data
                    : [];

                /*
             * Sebelum bayaran:
             * selected_activities mengandungi semua aktiviti.
             *
             * Selepas split:
             * setiap application hanya mempunyai satu aktiviti.
             */
                $activities =
                    $draftData['selected_activities']
                    ?? $draftData['processing_invoice_activities']
                    ?? $draftData['original_selected_activities']
                    ?? [];

                if (!is_array($activities)) {
                    $activities = [];
                }

                $activities = collect($activities)
                    ->map(
                        fn($item) => trim((string) $item)
                    )
                    ->filter()
                    ->values()
                    ->all();

                $activityName =
                    $activities[$currentIndex]
                    ?? $activities[0]
                    ?? $application?->activity_name
                    ?? $application?->activity_details
                    ?? '-';

                $activityCounters[$applicationId] =
                    $currentIndex + 1;

                return [
                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_no' =>
                    $invoice->invoice_no,

                    'payment_type' =>
                    $invoice->payment_type
                        ?? self::PROCESSING_PAYMENT_TYPE,

                    'amount' =>
                    (float) $invoice->total_amount,

                    'amount_display' =>
                    'RM '
                        . number_format(
                            $invoice->total_amount,
                            2
                        ),

                    'invoice_date' =>
                    optional($invoice->invoice_date)
                        ->format('d/m/Y')
                        ?? '-',

                    'due_date' =>
                    optional($invoice->due_date)
                        ->format('d/m/Y')
                        ?? '-',

                    'status' =>
                    $invoice->status,

                    'paid' =>
                    $this->isPaidInvoice($invoice),

                    'security_refund_status' =>
                    $invoice->security_refund_status
                        ?? 'not_requested',

                    'security_refund_requested_at' =>
                    optional(
                        $invoice->security_refund_requested_at
                    )->toDateTimeString(),

                    'security_refunded_at' =>
                    optional(
                        $invoice->security_refunded_at
                    )->toDateTimeString(),

                    'security_refunded_by' =>
                    $invoice->security_refunded_by,

                    'security_refund_note' =>
                    $invoice->security_refund_note,

                    'application_id' =>
                    $invoice->application_id,

                    'application_ref_no' =>
                    $application?->application_ref_no,

                    'application_no' =>
                    $application?->application_ref_no,

                    'application_ref_nos' =>
                    $application?->application_ref_no
                        ? [
                            $application
                                ->application_ref_no,
                        ]
                        : [],

                    'application_nos' =>
                    $application?->application_ref_no
                        ? [
                            $application
                                ->application_ref_no,
                        ]
                        : [],

                    /*
                 * Aktiviti khusus untuk invois ini.
                 */
                    'activity_name' =>
                    $activityName,

                    'activity_details' =>
                    $activityName,
                ];
            })
            ->values()
            ->all();
    }

    private function formatReceiptItems(array $applicationIds): array
    {
        return LsankReceipt::query()
            ->with(['invoice.application', 'payment'])
            ->whereHas('invoice', fn($query) => $query->whereIn('application_id', $applicationIds))
            ->latest('receipt_id')
            ->get()
            ->map(function (LsankReceipt $receipt) {
                $invoice = $receipt->invoice;
                $payment = $receipt->payment;
                $application = $invoice?->application;
                $amount = (float) ($receipt->amount ?? $payment?->amount ?? $invoice?->total_amount ?? 0);
                $paidAt = $payment?->payment_date ?? $receipt->receipt_date ?? $receipt->created_at;
                return [
                    'receipt_id' => $receipt->receipt_id,
                    'receipt_no' => $receipt->receipt_no,
                    'application_id' => $invoice?->application_id,
                    'application_ref_no' => $application?->application_ref_no,
                    'application_no' => $application?->application_ref_no,
                    'file_no' => $application?->application_ref_no,
                    'invoice_id' => $invoice?->invoice_id,
                    'invoice_no' => $invoice?->invoice_no ?? '-',
                    'payment_id' => $payment?->payment_id,
                    'payment_type' => $invoice?->payment_type ?? self::PROCESSING_PAYMENT_TYPE,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'amount_display' => 'RM ' . number_format($amount, 2),
                    'paid_amount_display' => 'RM ' . number_format($amount, 2),
                    'receipt_date' => optional($receipt->receipt_date)->format('d/m/Y') ?? '-',
                    'paid_date' => optional($paidAt)->format('d/m/Y') ?? '-',
                    'paid_at' => optional($paidAt)->toDateTimeString(),
                    'status' => $receipt->status ?? 'valid',
                    'paid' => true,
                    'activity_name' => $application?->activity_name ?? $application?->activity_details ?? '-',
                    'activity_details' => $application?->activity_details ?? $application?->activity_name ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    private function formatLicense(
        LsankApplication $application
    ): ?array {
        $license = $application->license;

        if (!$license) {
            return null;
        }

        $terminationRequest = $license->terminationRequest;

        return [
            'license_id' => $license->license_id,
            'license_no' => $license->license_no,
            'file_no' => $license->file_no ?? $application->application_ref_no,
            'application_id' => $license->application_id,
            'holder_name' => $license->holder_name,
            'license_type' => $license->license_type,
            'activity_name' => $license->activity_name ?? $application->activity_name ?? '-',
            'activity_location' => $license->activity_location ?? $application->activity_location ?? '-',
            'start_date' => optional($license->start_date)->format('Y-m-d'),
            'start_date_display' => optional($license->start_date)->format('d/m/Y'),
            'expiry_date' => optional($license->expiry_date)->format('Y-m-d'),
            'expiry_date_display' => optional($license->expiry_date)->format('d/m/Y'),
            'status' => $license->display_status,
            'license_status_id' => $license->license_status_id,
            'qr_code_path' => $license->qr_code_path,
            'license_pdf_path' => $license->license_pdf_path,
            'generated_at' => optional($license->generated_at)->toDateTimeString(),
            'termination_request' => $terminationRequest
                ? [
                    'termination_request_id' =>
                    $terminationRequest->termination_request_id,

                    'license_id' =>
                    $terminationRequest->license_id,

                    'application_id' =>
                    $terminationRequest->application_id,

                    'application_type' =>
                    $terminationRequest->application_type,

                    'status' =>
                    $terminationRequest->termination_status,

                    'reason' =>
                    $terminationRequest->reason,

                    'requested_at' => optional(
                        $terminationRequest->requested_at
                    )?->toIso8601String(),

                    'decided_by_user_id' =>
                    $terminationRequest->decided_by_user_id,

                    'director_remark' =>
                    $terminationRequest->director_remark,

                    'rejection_reason' =>
                    $terminationRequest->termination_status === 'rejected'
                        ? $terminationRequest->director_remark
                        : null,

                    'decided_at' => optional(
                        $terminationRequest->decided_at
                    )?->toIso8601String(),

                    'security_refund_status' =>
                    $terminationRequest->security_refund_status,

                    'security_refund_amount' =>
                    $terminationRequest->security_refund_amount,

                    'security_refund_reference' =>
                    $terminationRequest->security_refund_reference,

                    'security_refund_note' =>
                    $terminationRequest->security_refund_note,

                    'security_refunded_at' => optional(
                        $terminationRequest->security_refunded_at
                    )?->toIso8601String(),
                ]
                : null,
        ];
    }

    private function resolveBatchApplicationIds(LsankApplication $application, array $draftData): array
    {
        $ids = [$application->application_id];
        $splitBatchId = $draftData['split_batch_id'] ?? null;

        if (!$splitBatchId) {
            return $ids;
        }

        $batchIds = LsankApplication::query()
            ->where('user_id', $application->user_id)
            ->where('application_type_id', $application->application_type_id)
            ->where('draft_data->split_batch_id', $splitBatchId)
            ->pluck('application_id')
            ->values()
            ->all();

        return array_values(array_unique(array_merge($ids, $batchIds)));
    }

    private function resolveBatchApplicationRefs(LsankApplication $application, array $draftData): array
    {
        $splitBatchId = $draftData['split_batch_id'] ?? null;

        if (!$splitBatchId) {
            return [$application->application_ref_no];
        }

        $refs = LsankApplication::query()
            ->where('user_id', $application->user_id)
            ->where('application_type_id', $application->application_type_id)
            ->where('draft_data->split_batch_id', $splitBatchId)
            ->orderBy('application_id')
            ->pluck('application_ref_no')
            ->filter()
            ->values()
            ->all();

        return !empty($refs) ? $refs : [$application->application_ref_no];
    }

    private function guardOwnedWaterApplication(Request $request, LsankApplication $application): void
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            abort(404, 'Permohonan tidak dijumpai.');
        }

        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        if ((int) $application->application_type_id !== (int) $typeId) {
            abort(404, 'Permohonan badan perairan tidak dijumpai.');
        }
    }

    private function guardInvoiceOwnership(
        Request $request,
        LsankApplication $application,
        LsankInvoice $invoice
    ): void {
        if ((int) $invoice->application_id !== (int) $application->application_id) {
            abort(422, 'Invois tidak sepadan dengan permohonan ini.');
        }

        if ((int) $invoice->user_id !== (int) $request->user()->user_id) {
            abort(404, 'Invois tidak dijumpai.');
        }
    }

    private function createPayment(LsankInvoice $invoice, string $referencePrefix): LsankPayment
    {
        return LsankPayment::updateOrCreate(
            ['invoice_id' => $invoice->invoice_id],
            [
                'payment_method_id' => null,
                'amount' => (float) $invoice->total_amount,
                'payment_status' => 'successful',
                'payment_date' => now(),
                'transaction_ref_no' => $referencePrefix . '-' . now()->format('YmdHis'),
            ]
        );
    }

    private function createReceipt(
        LsankInvoice $invoice,
        LsankPayment $payment,
        string $feeCode
    ): LsankReceipt {
        $runningNumber = $this->nextReceiptRunningNumber();
        $receiptNo = 'RESIT-'
            . now()->format('Y')
            . '-'
            . str_pad((string) $runningNumber, 4, '0', STR_PAD_LEFT)
            . '-'
            . $feeCode;

        return LsankReceipt::updateOrCreate(
            ['invoice_id' => $invoice->invoice_id],
            [
                'payment_id' => $payment->payment_id,
                'receipt_no' => $receiptNo,
                'amount' => (float) $invoice->total_amount,
                'receipt_date' => now()->toDateString(),
                'receipt_pdf_path' => null,
                'status' => 'valid',
            ]
        );
    }

    private function createPaidSplitApplication(
        LsankApplication $lockedApplication,
        array $draftData,
        string $selectedActivity,
        Collection $remainingActivities,
        string $splitBatchId
    ): LsankApplication {
        $statusId = $this->applicationStatusId('in_process', 'Dalam Proses', 3);
        $originalApplicationId = $lockedApplication->application_id;

        if ($remainingActivities->isNotEmpty()) {
            $paidApplication = $lockedApplication->replicate();
            $paidApplication->exists = false;
            $paidApplication->application_id = null;
            $paidApplication->application_ref_no = $this->generateApplicationFileNo(
                $this->waterSectionCode($selectedActivity),
                $this->districtCode($lockedApplication->district)
            );
        } else {
            $paidApplication = $lockedApplication;

            if (
                empty($paidApplication->application_ref_no) ||
                str_starts_with(strtoupper($paidApplication->application_ref_no), 'DRAF-')
            ) {
                $paidApplication->application_ref_no = $this->generateApplicationFileNo(
                    $this->waterSectionCode($selectedActivity),
                    $this->districtCode($paidApplication->district)
                );
            }
        }

        $paidDraftData = $this->waterDraftDataForActivity($draftData, $selectedActivity);
        $paidDraftData['selected_activities'] = [$selectedActivity];
        $paidDraftData['processing_invoice_activities'] = [$selectedActivity];
        $paidDraftData['original_selected_activities'] = $this->resolveSelectedActivities(
            $lockedApplication,
            $draftData
        );
        $paidDraftData['split_batch_id'] = $splitBatchId;
        $paidDraftData['is_split_child'] = true;
        $paidDraftData['is_split_parent'] = $remainingActivities->isEmpty();
        $paidDraftData['split_from_application_id'] = $originalApplicationId;

        $paidApplication->draft_data = $paidDraftData;
        $paidApplication->submitted_data = $paidDraftData;
        $paidApplication->activity_name = $selectedActivity;
        $paidApplication->activity_type = $selectedActivity;
        $paidApplication->activity_details = $selectedActivity;
        $paidApplication->application_status_id = $statusId;
        $paidApplication->application_status = LsankApplication::STATUS_DALAM_PROSES;
        $paidApplication->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
        $paidApplication->submitted_at = now();
        $paidApplication->remarks = trim(
            ($paidApplication->remarks ?? '')
                . "\nBayaran satu invois berjaya pada "
                . now()->format('d/m/Y H:i')
        );
        $paidApplication->save();

        $this->syncWaterBodyForActivity($paidApplication, $selectedActivity);

        if ($remainingActivities->isNotEmpty()) {
            $remainingDraftData = $draftData;
            $remainingDraftData['selected_activities'] = $remainingActivities->all();
            $remainingDraftData['processing_invoice_activities'] = $remainingActivities->all();
            $remainingDraftData['original_selected_activities'] = $this->resolveSelectedActivities(
                $lockedApplication,
                $draftData
            );
            $remainingDraftData['split_batch_id'] = $splitBatchId;
            $remainingDraftData['is_split_parent'] = true;
            $remainingDraftData['is_split_child'] = false;

            $lockedApplication->draft_data = $remainingDraftData;
            $lockedApplication->activity_name = $remainingActivities->first();
            $lockedApplication->activity_type = $remainingActivities->first();
            $lockedApplication->activity_details = $remainingActivities->first();
            $lockedApplication->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
            $lockedApplication->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
            $lockedApplication->submitted_at = null;
            $lockedApplication->save();

            $this->syncWaterBodyForActivity(
                $lockedApplication,
                (string) $remainingActivities->first()
            );
        }

        return $paidApplication;
    }

    private function resolveSelectedActivities(
        LsankApplication $application,
        array $draftData
    ): array {
        /*
     * selected_activities ialah sumber utama ketika permohonan
     * belum dibayar.
     *
     * Selepas satu invois dibayar, selected_activities pada
     * permohonan induk akan mengandungi aktiviti yang masih belum
     * dibayar sahaja.
     */
        $activities = $draftData['selected_activities']
            ?? ($draftData['meta']['selected_activities'] ?? null)
            ?? $draftData['processing_invoice_activities']
            ?? $draftData['original_selected_activities']
            ?? [];

        if (!is_array($activities) || empty($activities)) {
            $activityDetails = trim(
                (string) (
                    $application->activity_details
                    ?? ''
                )
            );

            if ($activityDetails !== '') {
                $activities = collect(
                    preg_split('/[,;|]/', $activityDetails)
                )
                    ->map(
                        fn($item) => trim((string) $item)
                    )
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        if (!is_array($activities) || empty($activities)) {
            $activities = [
                $application->activity_name
                    ?? $application->activity_type
                    ?? 'Aktiviti Rekreasi Sukan Air',
            ];
        }

        $activities = collect($activities)
            ->map(
                fn($item) => trim((string) $item)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        return !empty($activities)
            ? $activities
            : ['Aktiviti Rekreasi Sukan Air'];
    }

    private function fillApplicationFields(
        LsankApplication $application,
        array $validated,
        Request $request
    ): void {
        $application->current_step = $validated['current_step'] ?? 0;
        $application->draft_data = $this->normalizeWaterDraftData($validated, $request, $application);
        $application->remarks = null;
        $application->license_type = self::TYPE_NAME;
        $application->activity_type = $validated['activity_name'] ?? null;
        $application->application_type = 'water';
        $application->applicant_name = $validated['applicant_name'] ?? '-';
        $application->business_name = $validated['company_name'] ?? null;
        $application->phone = $validated['phone_no'] ?? $validated['phone'] ?? null;
        $application->email = $validated['email'] ?? null;
        $application->applicant_type = $validated['applicant_type'] ?? null;
        $application->identity_no = $validated['identity_no'] ?? null;
        $application->phone_no = $validated['phone_no'] ?? $validated['phone'] ?? null;
        $application->address = $validated['address'] ?? null;
        $application->company_name = $validated['company_name'] ?? null;
        $application->registration_no = $validated['registration_no'] ?? null;
        $application->business_address = $validated['business_address'] ?? null;
        $application->business_phone = $validated['business_phone'] ?? null;
        $application->business_email = $validated['business_email'] ?? null;
        $application->responsible_officer_name = $validated['responsible_officer_name'] ?? null;
        $application->responsible_officer_phone = $validated['responsible_officer_phone'] ?? null;
        $application->responsible_officer_position = $validated['responsible_officer_position'] ?? null;
        $application->officers = $validated['officers'] ?? [];
        $application->activity_type_id = $validated['activity_type_id'] ?? null;
        $application->activity_name = $validated['activity_name'] ?? null;
        $application->district = $validated['district'] ?? null;
        $application->activity_location = $validated['activity_location'] ?? null;
        $application->longitude = $validated['longitude'] ?? null;
        $application->latitude = $validated['latitude'] ?? null;
        $application->operating_days = $validated['operating_days'] ?? null;
        $application->operating_time = $validated['operating_time'] ?? null;
        $application->activity_details = $validated['activity_details'] ?? null;
        $application->recreation_details = $validated['recreation_details'] ?? [];
    }

    private function saveWaterBody(LsankApplication $application, array $validated): void
    {
        $waterBody = LsankWaterBodyApplication::firstOrNew([
            'application_id' => $application->application_id,
        ]);

        $waterBody->activity_type_id = $validated['activity_type_id'] ?? null;
        $waterBody->activity_location = $validated['activity_location'] ?? null;
        $waterBody->longitude = $validated['longitude'] ?? null;
        $waterBody->latitude = $validated['latitude'] ?? null;
        $waterBody->operating_days = $validated['operating_days'] ?? null;
        $waterBody->operating_time = $validated['operating_time'] ?? null;
        $waterBody->motorized_fee = $validated['motorized_fee'] ?? 0;
        $waterBody->non_motorized_fee = $validated['non_motorized_fee'] ?? 0;
        $waterBody->activity_details = $validated['activity_details'] ?? null;
        $waterBody->save();
    }

    private function isPaidInvoice(LsankInvoice $invoice): bool
    {
        return strtolower(trim((string) $invoice->status)) === 'paid';
    }

    private function normalizeWaterDraftData(
        array $validated,
        Request $request,
        ?LsankApplication $application = null
    ): array {
        $incoming = $validated['draft_data']
            ?? $request->input('draft_data')
            ?? [];

        $incoming = is_array($incoming)
            ? $incoming
            : [];

        $existing = (
            $application &&
            is_array($application->draft_data)
        )
            ? $application->draft_data
            : [];

        $draftData = array_replace_recursive(
            $existing,
            $incoming
        );

        /*
     * Utamakan selected_activities yang dihantar oleh Flutter.
     * Jangan ambil original_selected_activities dahulu kerana
     * nilainya mungkin data lama.
     */
        $selectedActivities =
            $incoming['selected_activities']
            ?? ($incoming['meta']['selected_activities'] ?? null)
            ?? $draftData['selected_activities']
            ?? ($draftData['meta']['selected_activities'] ?? null)
            ?? $this->normalizeStringList(
                $validated['activity_details'] ?? null
            );

        if (
            !is_array($selectedActivities) ||
            empty($selectedActivities)
        ) {
            $selectedActivities = [
                $validated['activity_name']
                    ?? $application?->activity_name
                    ?? 'Aktiviti Rekreasi Sukan Air',
            ];
        }

        $selectedActivities = collect($selectedActivities)
            ->map(
                fn($item) => trim((string) $item)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $isSplitChild =
            ($draftData['is_split_child'] ?? false) === true;

        $draftData['meta'] = array_replace_recursive(
            is_array($draftData['meta'] ?? null)
                ? $draftData['meta']
                : [],
            [
                'module' => 'water',

                'step' =>
                $validated['current_step']
                    ?? $draftData['step']
                    ?? $draftData['meta']['step']
                    ?? 0,

                'current_step' =>
                $validated['current_step']
                    ?? $draftData['current_step']
                    ?? $draftData['meta']['current_step']
                    ?? 0,

                'selected_activities' =>
                $selectedActivities,

                'applicant_type' =>
                $validated['applicant_type']
                    ?? $draftData['applicant_type']
                    ?? null,

                'is_one_off' =>
                $draftData['is_one_off']
                    ?? $draftData['meta']['is_one_off']
                    ?? false,

                'license_duration_year' =>
                $draftData['license_duration_year']
                    ?? $draftData['meta']['license_duration_year']
                    ?? 1,
            ]
        );

        $draftData['selected_activities'] =
            $selectedActivities;

        /*
     * Ketika masih draf atau sebelum split, sentiasa kemas kini
     * senarai aktiviti asal dan senarai untuk invois.
     */
        if (!$isSplitChild) {
            $draftData['original_selected_activities'] =
                $selectedActivities;

            $draftData['processing_invoice_activities'] =
                $selectedActivities;
        }

        $draftData['recreation_details'] = is_array(
            $draftData['recreation_details'] ?? null
        )
            ? $draftData['recreation_details']
            : ($validated['recreation_details'] ?? []);

        $draftData['vessel_details'] = is_array(
            $draftData['vessel_details'] ?? null
        )
            ? $draftData['vessel_details']
            : [];

        $draftData['cage_details'] = is_array(
            $draftData['cage_details'] ?? null
        )
            ? $draftData['cage_details']
            : [];

        $draftData['construction_details'] = is_array(
            $draftData['construction_details'] ?? null
        )
            ? $draftData['construction_details']
            : [];

        return $draftData;
    }

    private function normalizeStringList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/[,;\/]/', $value))
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function waterDraftDataForActivity(array $draftData, string $activity): array
    {
        $draftData['selected_activities'] = [$activity];

        if (isset($draftData['meta']) && is_array($draftData['meta'])) {
            $draftData['meta']['selected_activities'] = [$activity];
        }

        if ($activity !== 'Aktiviti Rekreasi Sukan Air') {
            $draftData['recreation_details'] = [];
            $draftData['borang_c'] = [];
        }
        if ($activity !== 'Aktiviti Vesel Rekreasi') {
            $draftData['vessel_details'] = [];
            $draftData['vessel_types_by_index'] = [];
            $draftData['borang_d'] = [];
        }
        if ($activity !== 'Aktiviti Sangkar') {
            $draftData['cage_details'] = [];
            $draftData['borang_f'] = [];
        }
        if ($activity !== 'Aktiviti Binaan') {
            $draftData['construction_details'] = [];
            $draftData['borang_g'] = [];
        }

        return $draftData;
    }

    private function syncWaterBodyForActivity(LsankApplication $application, string $activity): void
    {
        $waterBody = LsankWaterBodyApplication::firstOrNew([
            'application_id' => $application->application_id,
        ]);
        $waterBody->activity_type_id = $application->activity_type_id;
        $waterBody->activity_location = $application->activity_location;
        $waterBody->longitude = $application->longitude;
        $waterBody->latitude = $application->latitude;
        $waterBody->operating_days = $application->operating_days;
        $waterBody->operating_time = $application->operating_time;
        $waterBody->motorized_fee = 0;
        $waterBody->non_motorized_fee = 0;
        $waterBody->activity_details = $activity;
        $waterBody->save();
    }

    private function calculateWaterFees(LsankApplication $application): array
    {
        $draftData = is_array($application->draft_data) ? $application->draft_data : [];
        $meta = is_array($draftData['meta'] ?? null) ? $draftData['meta'] : [];
        $isOneOff = ($draftData['is_one_off'] ?? $meta['is_one_off'] ?? false) === true;
        $selectedActivities = $draftData['selected_activities'] ?? $meta['selected_activities'] ?? [];
        $selectedActivities = is_array($selectedActivities) ? $selectedActivities : [];
        $licenseDurationYear = max(1, (int) ($draftData['license_duration_year'] ?? $meta['license_duration_year'] ?? 1));
        $count = max(1, count($selectedActivities));
        $securityFee = $isOneOff
            ? (in_array('Aktiviti Binaan', $selectedActivities, true) ? 1000 : 0)
            : 1000 * $count;
        $licenseFee = $isOneOff ? 250 * $count : 500 * $licenseDurationYear * $count;

        return [
            'processing_fee' => 150 * $count,
            'security_fee' => $securityFee,
            'license_fee' => $licenseFee,
            'charge_fee' => 0,
            'charge_items' => [],
            'is_one_off' => $isOneOff,
            'license_duration_year' => $licenseDurationYear,
            'license_activity_count' => $count,
            'total_after_approval' => $securityFee + $licenseFee,
        ];
    }

    private function displayApplicationStatus(?string $status): string
    {
        return match ($status) {
            LsankApplication::STATUS_DRAF => 'Draf',
            LsankApplication::STATUS_FI_PEMPROSESAN => 'Fi Pemprosesan',
            LsankApplication::STATUS_DALAM_PROSES => 'Dalam Proses',
            LsankApplication::STATUS_LULUS => 'Lulus',
            LsankApplication::STATUS_GAGAL => 'Ditolak',
            default => 'Draf',
        };
    }

    private function displayPaymentStatus(?string $status): string
    {
        return match ($status) {
            LsankApplication::PAYMENT_BELUM_BAYAR => 'Belum Bayar',
            LsankApplication::PAYMENT_MENUNGGU_BAYARAN => 'Menunggu Bayaran',
            LsankApplication::PAYMENT_SUDAH_BAYAR => 'Sudah Bayar',
            default => $status ?? 'Belum Bayar',
        };
    }

    private function generateInvoiceNoByRunningNumber(int $runningNumber, string $feeTypeCode): string
    {
        return 'INVOIS-'
            . now()->format('Y')
            . '-'
            . str_pad((string) $runningNumber, 4, '0', STR_PAD_LEFT)
            . '-'
            . $feeTypeCode;
    }

    private function nextInvoiceRunningNumber(): int
    {
        $year = now()->format('Y');
        $latestInvoice = LsankInvoice::query()
            ->where('invoice_no', 'like', 'INVOIS-' . $year . '-%')
            ->orderByDesc('invoice_id')
            ->lockForUpdate()
            ->first();

        if (!$latestInvoice || empty($latestInvoice->invoice_no)) {
            return 1;
        }

        $parts = explode('-', $latestInvoice->invoice_no);
        $running = count($parts) >= 3 ? (int) $parts[2] : 0;
        return $running > 0 ? $running + 1 : 1;
    }

    private function generateApplicationFileNo(string $sectionCode, string $districtCode): string
    {
        $running = $this->nextApplicationFileRunningNumber($sectionCode, $districtCode);
        return $sectionCode . '/' . $districtCode . '/' . str_pad((string) $running, 4, '0', STR_PAD_LEFT);
    }

    private function nextApplicationFileRunningNumber(string $sectionCode, string $districtCode): int
    {
        $prefix = $sectionCode . '/' . $districtCode . '/';
        $latest = LsankApplication::query()
            ->where('application_ref_no', 'like', $prefix . '%')
            ->orderByDesc('application_id')
            ->lockForUpdate()
            ->first();

        if (!$latest || empty($latest->application_ref_no)) {
            return 1;
        }

        $parts = explode('/', $latest->application_ref_no);
        return count($parts) >= 3 ? ((int) $parts[2]) + 1 : 1;
    }

    private function waterSectionCode(?string $activity): string
    {
        $value = strtolower(trim((string) $activity));
        return match (true) {
            str_contains($value, 'rekreasi sukan air') => '600-15',
            str_contains($value, 'vesel rekreasi') => '600-16',
            str_contains($value, 'sangkar') => '600-18',
            str_contains($value, 'binaan') => '600-19',
            default => '600-15',
        };
    }

    private function districtCode(?string $district): string
    {
        $value = strtolower(trim((string) $district));
        return match (true) {
            str_contains($value, 'kota setar') => '1',
            str_contains($value, 'kuala muda') => '2',
            str_contains($value, 'kulim') => '3',
            str_contains($value, 'kubang pasu') => '4',
            str_contains($value, 'baling') => '5',
            str_contains($value, 'sik') => '6',
            str_contains($value, 'padang terap') => '7',
            str_contains($value, 'langkawi') => '8',
            str_contains($value, 'yan') => '9',
            str_contains($value, 'bandar baharu') => '10',
            str_contains($value, 'pendang') => '11',
            str_contains($value, 'pokok sena') => '12',
            default => '0',
        };
    }

    private function nextReceiptRunningNumber(): int
    {
        $year = now()->format('Y');
        $latest = LsankReceipt::query()
            ->where('receipt_no', 'like', 'RESIT-' . $year . '-%')
            ->orderByDesc('receipt_id')
            ->lockForUpdate()
            ->first();

        if (!$latest || empty($latest->receipt_no)) {
            return 1;
        }

        $parts = explode('-', $latest->receipt_no);
        $running = count($parts) >= 3 ? (int) $parts[2] : 0;
        return $running > 0 ? $running + 1 : 1;
    }

    private function generateDraftReferenceNo(int $userId): string
    {
        do {
            $reference = 'DRAF-' . $userId . '-' . now()->format('YmdHisv');
        } while (
            LsankApplication::query()
            ->where('application_ref_no', $reference)
            ->exists()
        );

        return $reference;
    }
}
