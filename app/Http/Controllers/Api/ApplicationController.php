<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use App\Models\LsankInvoice;
use App\Models\LsankReceipt;
use Illuminate\Http\Request;


class ApplicationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | User Side - Store Water Application
    |--------------------------------------------------------------------------
    | Legacy route. Flow baru untuk water akan guna WaterApplicationController:
    | saveDraft -> generateInvoice -> pay
    |--------------------------------------------------------------------------
    */


    public function storeWaterApplication(Request $request)
    {
        $request->validate([
            'applicant_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'activity_type' => 'nullable|string|max:255',
            'draft_data' => 'nullable|array',
        ]);


        $application = LsankApplication::create([
            'application_ref_no' => $this->generateReferenceNo(),
            'user_id' => $request->user()->user_id,


            'applicant_name' => $request->applicant_name,
            'business_name' => $request->business_name,
            'phone' => $request->phone,
            'email' => $request->email,


            'license_type' => 'Aktiviti Badan Perairan',
            'activity_type' => $request->activity_type ?? 'Tidak Dinyatakan',
            'application_type' => 'water',


            'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
            'application_status' => LsankApplication::STATUS_DALAM_PROSES,


            'submitted_at' => now(),
            'draft_data' => $request->draft_data,
            'submitted_data' => $request->draft_data,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Permohonan badan perairan berjaya dihantar.',
            'application' => $this->formatApplication($application),
            'data' => $this->formatApplication($application),
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | User Side - My Applications
    |--------------------------------------------------------------------------
    | User boleh nampak semua:
    | draf, fi_pemprosesan, dalam_proses, lulus, gagal
    |--------------------------------------------------------------------------
    */


    public function myApplications(Request $request)
    {
        $applications = $this->applicationBaseQuery()
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('application_id')
            ->get()
            ->map(fn($application) => $this->formatApplication($application));


        return response()->json([
            'success' => true,
            'applications' => $applications,
            'data' => $applications,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Side - Application List
    |--------------------------------------------------------------------------
    | Admin hanya nampak permohonan yang sudah masuk semakan:
    | dalam_proses, lulus, gagal
    |--------------------------------------------------------------------------
    */


    private function applicationBaseQuery()
    {
        return LsankApplication::with([
            'applicant',
            'type',
            'status',
            'waterBody',
            'effluent',
        ]);
    }


    private function adminEligibleQuery()
    {
        return $this->applicationBaseQuery()
            ->whereIn('application_status', [
                LsankApplication::STATUS_FI_PEMPROSESAN,
                LsankApplication::STATUS_DALAM_PROSES,
                LsankApplication::STATUS_LULUS,
                LsankApplication::STATUS_GAGAL,
            ]);
    }


    public function adminApplications()
    {
        $applications = $this->adminEligibleQuery()
            ->orderByDesc('application_id')
            ->get()
            ->map(fn($application) => $this->formatApplication($application));


        return response()->json([
            'success' => true,
            'applications' => $applications,
            'data' => $applications,
        ]);
    }


    public function adminWaterApplications()
    {
        $applications = $this->adminEligibleQuery()
            ->where(function ($query) {
                $query->where('license_type', 'Aktiviti Badan Perairan')
                    ->orWhere('application_type', 'water')
                    ->orWhere('application_category', 'water')
                    ->orWhere('application_type_id', 1)
                    ->orWhereHas('type', function ($typeQuery) {
                        $typeQuery->where('type_code', 'WATER');
                    })
                    ->orWhereHas('waterBody');
            })
            ->orderByDesc('application_id')
            ->get()
            ->map(fn($application) => $this->formatApplication($application));


        return response()->json([
            'success' => true,
            'applications' => $applications,
            'data' => $applications,
        ]);
    }


    public function adminEffluentApplications()
    {
        $applications = $this->adminEligibleQuery()
            ->where(function ($query) {
                $query->where('license_type', 'Aktiviti Pelepasan Efluen')
                    ->orWhere('application_type', 'effluent')
                    ->orWhere('application_category', 'effluent')
                    ->orWhere('application_type_id', 2)
                    ->orWhereHas('type', function ($typeQuery) {
                        $typeQuery->where('type_code', 'EFFLUENT');
                    })
                    ->orWhereHas('effluent');
            })
            ->orderByDesc('application_id')
            ->get()
            ->map(fn($application) => $this->formatApplication($application));


        return response()->json([
            'success' => true,
            'applications' => $applications,
            'data' => $applications,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Side - Application Detail
    |--------------------------------------------------------------------------
    */


    public function show($id)
    {
        $application = $this->adminEligibleQuery()
            ->where('application_id', $id)
            ->first();


        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai atau belum layak untuk semakan admin.',
            ], 404);
        }


        return response()->json([
            'success' => true,
            'application' => $this->formatApplication($application),
            'data' => $this->formatApplication($application),
        ]);
    }


    public function showWater($id)
    {
        $application = $this->adminEligibleQuery()
            ->where('application_id', $id)
            ->where(function ($query) {
                $query->where('license_type', 'Aktiviti Badan Perairan')
                    ->orWhere('application_type', 'water')
                    ->orWhere('application_category', 'water')
                    ->orWhere('application_type_id', 1)
                    ->orWhereHas('type', function ($typeQuery) {
                        $typeQuery->where('type_code', 'WATER');
                    })
                    ->orWhereHas('waterBody');
            })
            ->first();


        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan badan perairan tidak dijumpai atau belum layak untuk semakan admin.',
            ], 404);
        }


        return response()->json([
            'success' => true,
            'application' => $this->formatApplication($application),
            'data' => $this->formatApplication($application),
        ]);
    }


    public function showEffluent($id)
    {
        $application = $this->adminEligibleQuery()
            ->where('application_id', $id)
            ->where(function ($query) {
                $query->where('license_type', 'Aktiviti Pelepasan Efluen')
                    ->orWhere('application_type', 'effluent')
                    ->orWhere('application_category', 'effluent')
                    ->orWhere('application_type_id', 2)
                    ->orWhereHas('type', function ($typeQuery) {
                        $typeQuery->where('type_code', 'EFFLUENT');
                    })
                    ->orWhereHas('effluent');
            })
            ->first();


        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan efluen tidak dijumpai atau belum layak untuk semakan admin.',
            ], 404);
        }


        return response()->json([
            'success' => true,
            'application' => $this->formatApplication($application),
            'data' => $this->formatApplication($application),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Side - Status / Review
    |--------------------------------------------------------------------------
    | Admin hanya update:
    | dalam_proses -> lulus / gagal
    |--------------------------------------------------------------------------
    */


    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'application_status' => 'required|string|in:lulus,gagal,dalam_proses',
            'payment_status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);


        $application = LsankApplication::where('application_id', $id)->first();


        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }


        if (!in_array($application->application_status, [
            LsankApplication::STATUS_DALAM_PROSES,
            LsankApplication::STATUS_LULUS,
            LsankApplication::STATUS_GAGAL,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini belum layak untuk semakan admin.',
            ], 422);
        }


        $application->update([
            'application_status' => $request->application_status,
            'payment_status' => $request->payment_status ?? $application->payment_status,
            'remarks' => $request->remarks,
        ]);


        $fresh = $application->fresh();


        return response()->json([
            'success' => true,
            'message' => 'Status permohonan berjaya dikemaskini.',
            'application' => $this->formatApplication($fresh),
            'data' => $this->formatApplication($fresh),
        ]);
    }


    public function review(Request $request, $id)
    {
        $request->validate([
            'application_status' => 'nullable|string|in:lulus,gagal,dalam_proses',
            'payment_status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'review_save_type' => 'nullable|string|max:50',
            'report_status' => 'nullable|string|max:50',
            'activity_reports' => 'nullable|array',
            'review_data' => 'nullable|array',
            'security_amount' => 'nullable',
            'government_project' => 'nullable|string|max:255',
            'project_invoice_mode' => 'nullable|string|max:100',
            'invoice_generate' => 'nullable',
            'invoice_trigger' => 'nullable',
            'invoice_category' => 'nullable|string|max:100',
            'invoice_fee_caj' => 'nullable',
            'invoice_fee_lesen' => 'nullable',
            'invoice_fee_sekuriti' => 'nullable',
            'invoice_exempt' => 'nullable',
            'invoice_exempt_reason' => 'nullable|string',
            'invoice_manual_mode' => 'nullable',
            'invoice_manual_reason' => 'nullable|string',
            'license_start_date' => 'nullable|string|max:50',
            'license_end_date' => 'nullable|string|max:50',
            'assigned_to_name' => 'nullable|string|max:255',
            'assigned_to_email' => 'nullable|email|max:255',
            'assigned_to_role' => 'nullable|string|max:100',
            'workflow_stage' => 'nullable|string|max:100',

            'head_remark' => 'nullable|string',
            'head_feedback' => 'nullable|string',
            'head_feedback_target' => 'nullable|string|max:100',
            'head_officer_name' => 'nullable|string|max:255',
            'head_officer_email' => 'nullable|email|max:255',
        ]);


        $application = LsankApplication::where('application_id', $id)->first();


        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }


        if (!in_array($application->application_status, [
            LsankApplication::STATUS_DALAM_PROSES,
            LsankApplication::STATUS_LULUS,
            LsankApplication::STATUS_GAGAL,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini belum layak untuk semakan admin.',
            ], 422);
        }


        $existingReviewData = is_array($application->review_data)
            ? $application->review_data
            : [];


        $incomingReviewData = $request->review_data ?? [];


        if (!is_array($incomingReviewData)) {
            $incomingReviewData = [];
        }


        $workflowFields = [
            'assigned_to_name',
            'assigned_to_email',
            'assigned_to_role',
            'workflow_stage',

            'head_remark',
            'head_feedback',
            'head_feedback_target',
            'head_officer_name',
            'head_officer_email',

            'technical_feedback',
            'technical_feedback_target',
            'technical_feedback_target_name',
            'technical_feedback_target_email',
            'technical_officer_name',
            'technical_officer_email',

            'user_resubmission_required',
            'resubmit_duration_days',
            'resubmit_due_date',

            'director_remark',
            'director_feedback',
            'director_decision',
            'invoice_generate',
            'invoice_trigger',
            'invoice_category',
            'invoice_fee_caj',
            'invoice_fee_lesen',
            'invoice_fee_sekuriti',
            'invoice_exempt',
            'invoice_exempt_reason',
            'invoice_manual_mode',
            'invoice_manual_reason',

            'security_amount',
            'government_project',
            'project_invoice_mode',

            'license_start_date',
            'license_end_date',
        ];

        $workflowData = [];

        foreach ($workflowFields as $field) {
            if ($request->exists($field)) {
                $workflowData[$field] = $request->input($field);
            }
        }

        $reviewData = array_replace_recursive(
            $existingReviewData,
            $incomingReviewData,
            $workflowData
        );

        $existingMeta = is_array($reviewData['meta'] ?? null)
            ? $reviewData['meta']
            : [];

        if ($request->exists('review_save_type')) {
            $existingMeta['review_save_type'] =
                $request->input('review_save_type');
        }

        if ($request->exists('report_status')) {
            $existingMeta['report_status'] =
                $request->input('report_status');
        }

        if ($request->exists('workflow_stage')) {
            $existingMeta['workflow_stage'] =
                $request->input('workflow_stage');
        }

        $existingMeta['reviewed_at'] = now()->toDateTimeString();
        $existingMeta['reviewed_by_user_id'] =
            optional($request->user())->user_id;

        $reviewData['meta'] = $existingMeta;

        if ($request->exists('activity_reports')) {
            $reviewData['activity_reports'] =
                $request->input('activity_reports') ?? [];
        }


        $application->update([
            'application_status' => $request->application_status
                ?? LsankApplication::STATUS_DALAM_PROSES,
            'payment_status' => $request->payment_status
                ?? $application->payment_status,
            'remarks' => $request->remarks,
            'review_data' => $reviewData,
        ]);


        $fresh = $application->fresh();
        $finalInvoices = [];

        $isDirectorApproval =
            $fresh->isDirectorApproved()
            && $request->input('director_decision') === 'lulus'
            && $request->input('workflow_stage') === 'director_approved';

        if ($isDirectorApproval) {
            $finalInvoices =
                $this->createFinalInvoicesForApprovedApplication(
                    $fresh
                );

            $fresh = $fresh->fresh();
        }

        return response()->json([
            'success' => true,

            'message' => $isDirectorApproval
                ? (
                    count($finalInvoices) > 0
                    ? 'Permohonan diluluskan dan invois bayaran akhir berjaya dijana.'
                    : 'Permohonan diluluskan. Tiada invois bayaran akhir perlu dijana.'
                )
                : 'Semakan permohonan berjaya disimpan.',

            'application' =>
            $this->formatApplication($fresh),

            'data' =>
            $this->formatApplication($fresh),

            'final_invoices' => collect($finalInvoices)
                ->map(function (LsankInvoice $invoice) {
                    return [
                        'invoice_id' =>
                        $invoice->invoice_id,

                        'invoice_no' =>
                        $invoice->invoice_no,

                        'payment_type' =>
                        $invoice->payment_type,

                        'total_amount' =>
                        (float) $invoice->total_amount,

                        'status' =>
                        $invoice->status,
                    ];
                })
                ->values()
                ->all(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */


    private function generateReferenceNo(): string
    {
        $latestId = LsankApplication::max('application_id') ?? 0;


        return 'FAIL-' . now()->format('Y') . '-' . str_pad($latestId + 1, 4, '0', STR_PAD_LEFT);
    }


    private function displayApplicationStatus(?string $status): string
    {
        return match ($status) {
            LsankApplication::STATUS_DRAF => 'Draf',
            LsankApplication::STATUS_FI_PEMPROSESAN => 'Fi Pemprosesan',
            LsankApplication::STATUS_DALAM_PROSES => 'Dalam Proses',
            LsankApplication::STATUS_LULUS => 'Lulus',
            LsankApplication::STATUS_GAGAL => 'Gagal',
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


    private function formatApplication(?LsankApplication $application): array
    {
        if (!$application) {
            return [];
        }


        $draftData = is_array($application->draft_data)
            ? $application->draft_data
            : [];


        $splitBatchId = $draftData['split_batch_id'] ?? ($draftData['meta']['split_batch_id'] ?? null);


        $applicationRefNos = [$application->application_ref_no];


        if ($splitBatchId) {
            $applicationRefNos = LsankApplication::where('user_id', $application->user_id)
                ->where('application_type_id', $application->application_type_id)
                ->where('application_status', LsankApplication::STATUS_DALAM_PROSES)
                ->where('draft_data->split_batch_id', $splitBatchId)
                ->orderBy('application_id')
                ->pluck('application_ref_no')
                ->filter()
                ->values()
                ->all();
        }


        $processingInvoiceActivities =
            $draftData['processing_invoice_activities']
            ?? $draftData['original_selected_activities']
            ?? $draftData['selected_activities']
            ?? ($draftData['meta']['selected_activities'] ?? null)
            ?? [$application->activity_name ?? $application->activity_details ?? '-'];


        $invoices = LsankInvoice::query()
            ->where('application_id', $application->application_id)
            ->with([
                'receipt.payment',
            ])
            ->latest('invoice_id')
            ->get();

        $invoiceItems = $invoices
            ->map(function (LsankInvoice $invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_no' => $invoice->invoice_no,
                    'application_id' => $invoice->application_id,

                    'payment_type' => $invoice->payment_type
                        ?? 'Fi Pemprosesan',

                    'amount' => (float) ($invoice->total_amount ?? 0),

                    'amount_display' => 'RM ' . number_format(
                        (float) ($invoice->total_amount ?? 0),
                        2
                    ),

                    'invoice_date' => optional(
                        $invoice->invoice_date
                    )->format('d/m/Y') ?? '-',

                    'due_date' => optional(
                        $invoice->due_date
                    )->format('d/m/Y') ?? '-',

                    'status' => $invoice->status,

                    'paid' => strtolower(
                        (string) $invoice->status
                    ) === 'paid',
                ];
            })
            ->values()
            ->all();


        $invoiceIds = $invoices
            ->pluck('invoice_id')
            ->filter()
            ->values();

        $receiptItems = collect();

        if ($invoiceIds->isNotEmpty()) {
            $receiptItems = LsankReceipt::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->with([
                    'invoice',
                    'payment',
                    'payment.invoice',
                ])
                ->latest('receipt_id')
                ->get()
                ->map(function (LsankReceipt $receipt) use ($application) {
                    $invoice = $receipt->invoice
                        ?? optional($receipt->payment)->invoice;

                    $payment = $receipt->payment;

                    $invoiceId = $receipt->invoice_id
                        ?? optional($payment)->invoice_id
                        ?? optional($invoice)->invoice_id;

                    $invoiceNo = optional($invoice)->invoice_no;

                    $paymentType = optional($invoice)->payment_type
                        ?? 'Fi Pemprosesan';

                    $amount = (float) (
                        $receipt->amount
                        ?? optional($payment)->amount
                        ?? optional($invoice)->total_amount
                        ?? 0
                    );

                    $paidDate = $receipt->receipt_date
                        ?? optional($payment)->payment_date
                        ?? $receipt->created_at;

                    return [
                        'receipt_id' => $receipt->receipt_id,
                        'receipt_no' => $receipt->receipt_no,

                        'application_id' => $application->application_id,
                        'application_ref_no' => $application->application_ref_no,
                        'application_no' => $application->application_ref_no,
                        'file_no' => $application->application_ref_no,

                        'invoice_id' => $invoiceId,
                        'invoice_no' => $invoiceNo ?? '-',

                        'payment_id' => $receipt->payment_id,

                        'payment_type' => $paymentType,

                        'amount' => $amount,
                        'paid_amount' => $amount,

                        'amount_display' => 'RM ' . number_format(
                            $amount,
                            2
                        ),

                        'paid_amount_display' => 'RM ' . number_format(
                            $amount,
                            2
                        ),

                        'paid_date' => optional(
                            $paidDate
                        )->format('d/m/Y') ?? '-',

                        'receipt_date' => optional(
                            $receipt->receipt_date
                        )->format('d/m/Y') ?? optional(
                            $paidDate
                        )->format('d/m/Y') ?? '-',

                        'status' => $receipt->status ?? 'valid',

                        'paid' => true,

                        'invoice' => $invoice
                            ? [
                                'invoice_id' => $invoice->invoice_id,
                                'invoice_no' => $invoice->invoice_no,
                                'application_id' => $invoice->application_id,
                                'payment_type' => $invoice->payment_type,
                                'total_amount' => (float) $invoice->total_amount,
                                'status' => $invoice->status,
                            ]
                            : null,

                        'payment' => $payment
                            ? [
                                'payment_id' => $payment->payment_id,
                                'invoice_id' => $payment->invoice_id,
                                'amount' => (float) $payment->amount,
                                'payment_status' => $payment->payment_status,
                                'payment_date' => optional(
                                    $payment->payment_date
                                )->toDateTimeString(),

                                'invoice' => $invoice
                                    ? [
                                        'invoice_id' => $invoice->invoice_id,
                                        'invoice_no' => $invoice->invoice_no,
                                    ]
                                    : null,
                            ]
                            : null,
                    ];
                })
                ->values();
        }

        $receiptItems = $receiptItems->all();


        return [
            'id' => $application->application_id,
            'application_id' => $application->application_id,
            'application_no' => $application->application_ref_no,
            'application_ref_no' => $application->application_ref_no,


            'application_ref_nos' => $applicationRefNos,
            'application_nos' => $applicationRefNos,
            'processing_invoice_activities' => $processingInvoiceActivities,


            'user_id' => $application->user_id,
            'applicant_id' => $application->applicant_id,


            'applicant_name' => $application->applicant_name
                ?? optional($application->applicant)->applicant_name
                ?? $application->company_name
                ?? '-',


            'business_name' => $application->business_name
                ?? $application->company_name
                ?? optional(optional($application->applicant)->company)->company_name
                ?? '-',


            'phone' => $application->phone
                ?? $application->phone_no
                ?? $application->business_phone
                ?? '-',


            'email' => $application->email
                ?? $application->business_email
                ?? '-',


            'license_type' => $application->license_type
                ?? $this->displayLicenseType($application),


            'activity_type' => $application->activity_type
                ?? $application->activity_name
                ?? optional($application->waterBody)->activity_details
                ?? '-',


            'activity_name' => $application->activity_name
                ?? $application->activity_type
                ?? optional($application->waterBody)->activity_details
                ?? '-',


            'activity_details' => $application->activity_details
                ?? optional($application->waterBody)->activity_details
                ?? $application->activity_name
                ?? $application->activity_type
                ?? '-',


            'activity_location' => $application->activity_location
                ?? optional($application->waterBody)->activity_location
                ?? '-',


            'district' => $application->district ?? '-',


            'application_type' => $application->application_type,
            'application_category' => $application->application_category,


            'status_code' => $application->application_status,
            'status' => $this->displayApplicationStatus($application->application_status),


            'application_status' => $application->application_status,
            'application_status_display' => $this->displayApplicationStatus($application->application_status),


            'payment_status' => $application->payment_status,
            'payment_status_display' => $this->displayPaymentStatus($application->payment_status),


            'current_step' => $application->current_step ?? 0,
            'draft_data' => $application->draft_data,
            'review_data' => $application->review_data,
            'submitted_data' => $application->submitted_data,


            'remarks' => $application->remarks,


            'submitted_at' => optional($application->submitted_at)->toDateTimeString(),
            'submitted_date' => optional(
                $application->submitted_at ?? $application->created_at
            )->format('d M Y') ?? '-',


            'created_at' => optional($application->created_at)->toDateTimeString(),
            'updated_at' => optional($application->updated_at)->toDateTimeString(),


            'invoice_items' => $invoiceItems,
            'receipt_items' => $receiptItems,
        ];
    }


    private function displayLicenseType(LsankApplication $application): string
    {
        if (
            $application->application_type === 'water' ||
            $application->application_category === 'water' ||
            optional($application->type)->type_code === 'WATER' ||
            $application->waterBody
        ) {
            return 'Aktiviti Badan Perairan';
        }


        if (
            $application->application_type === 'effluent' ||
            $application->application_category === 'effluent' ||
            optional($application->type)->type_code === 'EFFLUENT' ||
            $application->effluent
        ) {
            return 'Aktiviti Pelepasan Efluen';
        }


        return '-';
    }
    private function createFinalInvoicesForApprovedApplication(
        LsankApplication $application
    ): array {
        $application->refresh();

        if (!$application->isDirectorApproved()) {
            return [];
        }

        $reviewData = is_array($application->review_data)
            ? $application->review_data
            : [];

        $isExempt = filter_var(
            $reviewData['invoice_exempt'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        /*
     * Aktiviti yang dikecualikan tidak mempunyai
     * Fi Lesen, Fi Caj atau Wang Sekuriti.
     */
        if ($isExempt) {
            $reviewData['final_invoice_status'] = 'exempt';
            $reviewData['final_invoice_ids'] = [];

            $application->review_data = $reviewData;
            $application->save();

            return [];
        }

        /*
     * Kod jenis fi:
     * 01 = Fi Pemprosesan
     * 02 = Fi Lesen
     * 03 = Fi Caj
     * 04 = Wang Sekuriti
     */
        $feeItems = [
            [
                'payment_type' => 'Fi Lesen',
                'fee_code' => '02',
                'amount' => $this->moneyValue(
                    $reviewData['invoice_fee_lesen'] ?? 0
                ),
            ],
            [
                'payment_type' => 'Fi Caj',
                'fee_code' => '03',
                'amount' => $this->moneyValue(
                    $reviewData['invoice_fee_caj'] ?? 0
                ),
            ],
            [
                'payment_type' => 'Wang Sekuriti',
                'fee_code' => '04',
                'amount' => $this->moneyValue(
                    $reviewData['invoice_fee_sekuriti']
                        ?? $reviewData['security_amount']
                        ?? 0
                ),
            ],
        ];

        /*
     * Jangan jana invois yang jumlahnya RM0.
     */
        $feeItems = collect($feeItems)
            ->filter(function (array $item) {
                return $item['amount'] > 0;
            })
            ->values();

        if ($feeItems->isEmpty()) {
            $reviewData['final_invoice_status'] = 'no_fee';
            $reviewData['final_invoice_ids'] = [];

            $application->review_data = $reviewData;
            $application->save();

            return [];
        }

        $createdInvoices = [];

        $nextRunningNumber = $this->nextInvoiceRunningNumber();

        foreach ($feeItems as $index => $feeItem) {
            /*
         * Elakkan invois berganda jika Pengarah
         * menekan butang Lulus lebih daripada sekali.
         */
            $invoice = LsankInvoice::where(
                'application_id',
                $application->application_id
            )
                ->where(
                    'payment_type',
                    $feeItem['payment_type']
                )
                ->first();

            if (!$invoice) {
                $invoice = LsankInvoice::create([
                    'application_id' =>
                    $application->application_id,

                    'license_id' => null,

                    'user_id' =>
                    $application->user_id,

                    'invoice_no' =>
                    $this->generateInvoiceNoByRunningNumber(
                        $nextRunningNumber + $index,
                        $feeItem['fee_code']
                    ),

                    'payment_type' =>
                    $feeItem['payment_type'],

                    'invoice_date' =>
                    now()->toDateString(),

                    'due_date' =>
                    now()->addDays(14)->toDateString(),

                    'total_amount' =>
                    $feeItem['amount'],

                    'status' => 'unpaid',
                ]);
            } elseif ($invoice->status !== 'paid') {
                /*
             * Kalau invois belum dibayar dan jumlah fi berubah,
             * kemas kini jumlah tanpa mencipta rekod baharu.
             */
                $invoice->total_amount =
                    $feeItem['amount'];

                $invoice->invoice_date =
                    now()->toDateString();

                $invoice->due_date =
                    now()->addDays(14)->toDateString();

                $invoice->status = 'unpaid';
                $invoice->save();
            }

            $createdInvoices[] = $invoice;
        }

        $reviewData['final_invoice_status'] =
            'pending_payment';

        $reviewData['final_invoice_ids'] =
            collect($createdInvoices)
            ->pluck('invoice_id')
            ->values()
            ->all();

        $reviewData['final_invoice_nos'] =
            collect($createdInvoices)
            ->pluck('invoice_no')
            ->values()
            ->all();

        $application->payment_status =
            LsankApplication::PAYMENT_MENUNGGU_BAYARAN;

        $application->review_data = $reviewData;
        $application->save();

        return $createdInvoices;
    }

    private function moneyValue(mixed $value): float
    {
        if ($value === null) {
            return 0;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return 0;
        }

        /*
     * Sokong nilai seperti:
     * 1000
     * 1,000.00
     * RM 1,000.00
     */
        $cleaned = preg_replace(
            '/[^0-9.\-]/',
            '',
            $text
        );

        if (
            $cleaned === null ||
            $cleaned === '' ||
            !is_numeric($cleaned)
        ) {
            return 0;
        }

        return max(
            0,
            round((float) $cleaned, 2)
        );
    }

    private function generateInvoiceNoByRunningNumber(
        int $runningNumber,
        string $feeTypeCode
    ): string {
        $year = now()->format('Y');

        $runningNo = str_pad(
            $runningNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        return 'INVOIS-'
            . $year
            . '-'
            . $runningNo
            . '-'
            . $feeTypeCode;
    }

    private function nextInvoiceRunningNumber(): int
    {
        $year = now()->format('Y');

        $latestInvoice = LsankInvoice::where(
            'invoice_no',
            'like',
            'INVOIS-' . $year . '-%'
        )
            ->orderByDesc('invoice_id')
            ->first();

        if (
            !$latestInvoice ||
            empty($latestInvoice->invoice_no)
        ) {
            return 1;
        }

        $parts = explode(
            '-',
            $latestInvoice->invoice_no
        );

        if (count($parts) < 3) {
            return 1;
        }

        $latestRunningNo = (int) $parts[2];

        if ($latestRunningNo < 1) {
            return 1;
        }

        return $latestRunningNo + 1;
    }
}
