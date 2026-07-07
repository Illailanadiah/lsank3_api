<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use App\Models\LsankInvoice;
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
            ->map(fn ($application) => $this->formatApplication($application));

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
            ->map(fn ($application) => $this->formatApplication($application));

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
            ->map(fn ($application) => $this->formatApplication($application));

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
            ->map(fn ($application) => $this->formatApplication($application));

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
            'application_status' => $request->application_status
                ?? LsankApplication::STATUS_DALAM_PROSES,
            'payment_status' => $request->payment_status
                ?? $application->payment_status,
            'remarks' => $request->remarks,
        ]);

        $fresh = $application->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Semakan permohonan berjaya disimpan.',
            'application' => $this->formatApplication($fresh),
            'data' => $this->formatApplication($fresh),
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

        $splitBatchId = $draftData['split_batch_id'] ?? null;

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
            ?? [$application->activity_name ?? $application->activity_details ?? '-'];

        $invoiceItems = LsankInvoice::where('application_id', $application->application_id)
            ->latest('invoice_id')
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_no' => $invoice->invoice_no,
                    'payment_type' => 'Fi Pemprosesan',
                    'amount' => (float) $invoice->total_amount,
                    'amount_display' => 'RM ' . number_format($invoice->total_amount, 2),
                    'invoice_date' => optional($invoice->invoice_date)->format('d/m/Y') ?? '-',
                    'due_date' => optional($invoice->due_date)->format('d/m/Y') ?? '-',
                    'status' => $invoice->status,
                    'paid' => $invoice->status === 'paid',
                ];
            })
            ->values()
            ->all();

        $year = optional($application->created_at)->format('Y') ?? now()->format('Y');
        $runningNo = str_pad($application->application_id, 4, '0', STR_PAD_LEFT);

        $receiptItems = [];

        if (
            in_array($application->application_status, [
                LsankApplication::STATUS_DALAM_PROSES,
                LsankApplication::STATUS_LULUS,
                LsankApplication::STATUS_GAGAL,
            ], true) ||
            $application->payment_status === LsankApplication::PAYMENT_SUDAH_BAYAR
        ) {
            $receiptItems[] = [
                'receipt_id' => $application->application_id,
                'receipt_no' => 'RESIT-' . $year . '-' . $runningNo . '-01',
                'payment_type' => 'Fi Pemprosesan',
                'amount' => 150,
                'amount_display' => 'RM 150.00',
                'paid_date' => optional($application->updated_at)->format('d/m/Y') ?? '-',
                'paid' => true,
            ];
        }

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
}