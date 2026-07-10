<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesApplicationData;
use App\Http\Controllers\Controller;
use App\Models\LsankApplicant;
use App\Models\LsankApplication;
use App\Models\LsankCompany;
use App\Models\LsankWaterBodyApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LsankInvoice;

class WaterApplicationController extends Controller
{
    use HandlesApplicationData;

    private const TYPE_CODE = 'WATER';
    private const TYPE_NAME = 'Aktiviti Badan Perairan';

    public function index(Request $request)
    {
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        $applications = LsankApplication::with([
                'applicant',
                'status',
                'type',
                'waterBody',
            ])
            ->where('user_id', $request->user()->user_id)
            ->where('application_type_id', $typeId)
            ->latest('application_id')
            ->get()
            ->map(function ($application) {
                $year = optional($application->created_at)->format('Y') ?? now()->format('Y');
                $runningNo = str_pad($application->application_id, 4, '0', STR_PAD_LEFT);

                $fees = $this->calculateWaterFees($application);

                $draftData = is_array($application->draft_data)
                    ? $application->draft_data
                    : [];

                $splitBatchId = $draftData['split_batch_id'] ?? null;

                $invoiceApplicationIds = [$application->application_id];

                if ($splitBatchId) {
                    $sameBatchApplicationIds = LsankApplication::where('user_id', $application->user_id)
                        ->where('application_type_id', $application->application_type_id)
                        ->where('application_status', LsankApplication::STATUS_DALAM_PROSES)
                        ->where('draft_data->split_batch_id', $splitBatchId)
                        ->pluck('application_id')
                        ->values()
                        ->all();

                    $invoiceApplicationIds = array_values(array_unique(array_merge(
                        $invoiceApplicationIds,
                        $sameBatchApplicationIds
                    )));
                }

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

               $invoiceItems = LsankInvoice::with('application')
                    ->where('application_id', $application->application_id)
                    ->orderBy('invoice_id')
                    ->get()
                    ->values()
                    ->map(function ($invoice, $itemIndex) use ($processingInvoiceActivities, $application) {
                        $invoiceApplication = $invoice->application;

                        $activityName = $invoiceApplication?->activity_name
                            ?? $invoiceApplication?->activity_details
                            ?? (
                                is_array($processingInvoiceActivities) && isset($processingInvoiceActivities[$itemIndex])
                                    ? $processingInvoiceActivities[$itemIndex]
                                    : ($application->activity_name ?? $application->activity_details ?? null)
                            );

                        $applicationRefNo = $invoiceApplication?->application_ref_no
                            ?? $application->application_ref_no;

                        return [
                            'invoice_id' => $invoice->invoice_id,
                            'invoice_no' => $invoice->invoice_no,
                            'payment_type' => $invoice->payment_type ?? 'Fi Pemprosesan',
                            'amount' => (float) $invoice->total_amount,
                            'amount_display' => 'RM ' . number_format($invoice->total_amount, 2),
                            'invoice_date' => optional($invoice->invoice_date)->format('d/m/Y') ?? '-',
                            'due_date' => optional($invoice->due_date)->format('d/m/Y') ?? '-',
                            'status' => $invoice->status,
                            'paid' => $invoice->status === 'paid',

                            'application_id' => $invoice->application_id,
                            'application_ref_no' => $applicationRefNo,
                            'application_no' => $applicationRefNo,
                            'application_ref_nos' => [$applicationRefNo],
                            'application_nos' => [$applicationRefNo],

                            'activity_name' => $activityName,
                            'activity_details' => $activityName,
                        ];
                    })
                    ->values()
                    ->all();

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

                    'applicant_name' => $application->applicant_name
                        ?? optional($application->applicant)->applicant_name
                        ?? '-',

                    'business_name' => $application->business_name
                        ?? $application->company_name
                        ?? optional(optional($application->applicant)->company)->company_name
                        ?? '-',

                    'phone' => $application->phone
                        ?? $application->phone_no
                        ?? '-',

                    'email' => $application->email ?? '-',

                    'license_type' => self::TYPE_NAME,

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

                    'status_code' => $application->application_status,
                    'status' => $this->displayApplicationStatus($application->application_status),

                    'application_status' => $application->application_status,
                    'application_status_display' => $this->displayApplicationStatus($application->application_status),

                    'payment_status' => $application->payment_status,
                    'payment_status_display' => $this->displayPaymentStatus($application->payment_status),

                    'current_step' => $application->current_step ?? 0,
                    'draft_data' => $application->draft_data,

                    'submitted_at' => optional($application->submitted_at)->toDateTimeString(),

                    'submitted_date' => optional(
                        $application->submitted_at ?? $application->created_at
                    )->format('d M Y') ?? '-',

                    'created_at' => optional($application->created_at)->toDateTimeString(),
                    'updated_at' => optional($application->updated_at)->toDateTimeString(),

                    'fees' => $fees,
                    'invoice_items' => $invoiceItems,
                    'receipt_items' => $receiptItems,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    public function saveDraft(Request $request)
    {
        $validated = $request->validate([
            'application_id' => ['nullable', 'integer'],

            'applicant_type' => ['nullable', 'string', 'max:100'],
            'applicant_name' => ['nullable', 'string', 'max:255'],
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
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
        $statusId = $this->applicationStatusId('draft', 'Draf', 1);
        $phoneColumn = $this->applicantPhoneColumn();

        return DB::transaction(function () use (
            $request,
            $validated,
            $user,
            $typeId,
            $statusId,
            $phoneColumn
        ) {
            $application = null;

            if (!empty($validated['application_id'])) {
                $application = LsankApplication::where('application_id', $validated['application_id'])
                    ->where('user_id', $user->user_id)
                    ->where('application_type_id', $typeId)
                    ->first();
            }

            $applicant = null;

            if ($application) {
                $applicant = LsankApplicant::where('applicant_id', $application->applicant_id)
                    ->first();
            }

            if (!$applicant) {
                $applicant = new LsankApplicant();
                $applicant->user_id = $user->user_id;
                $applicant->status = 'active';
            }

            $applicant->applicant_type = $this->normalizeApplicantType(
                $validated['applicant_type'] ?? null
            );
            $applicant->applicant_name = $validated['applicant_name'] ?? '-';
            $applicant->identity_no = $validated['identity_no'] ?? null;
            $applicant->email = $validated['email'] ?? null;
            $applicant->address = $validated['address'] ?? null;
            $applicant->{$phoneColumn} =
                $validated['phone_no'] ??
                $validated['phone'] ??
                null;
            $applicant->save();

            if (!empty($validated['company_name'])) {
                $company = LsankCompany::where('applicant_id', $applicant->applicant_id)
                    ->first();

                if (!$company) {
                    $company = new LsankCompany();
                    $company->applicant_id = $applicant->applicant_id;
                }

                $company->company_name = $validated['company_name'];
                $company->registration_no = $validated['registration_no'] ?? null;
                $company->business_address = $validated['business_address'] ?? null;
                $company->business_phone = $validated['business_phone'] ?? null;
                $company->business_email = $validated['business_email'] ?? null;
                $company->responsible_officer_name =
                    $validated['responsible_officer_name'] ?? null;
                $company->responsible_officer_phone =
                    $validated['responsible_officer_phone'] ?? null;
                $company->save();
            }

            if (!$application) {
                $application = new LsankApplication();
                $application->application_ref_no = $this->generateDraftReferenceNo($user->user_id);
                $application->user_id = $user->user_id;
                $application->application_type_id = $typeId;
                $application->application_category = 'new';
            }

            $application->applicant_id = $applicant->applicant_id;

            if ($application->exists && $application->application_status === LsankApplication::STATUS_FI_PEMPROSESAN) {
                $paymentStatusId = $this->applicationStatusId('payment', 'Fi Pemprosesan', 2);

                $application->application_status_id = $paymentStatusId;
                $application->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
                $application->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
            } elseif (!$application->exists || $application->application_status === LsankApplication::STATUS_DRAF) {
                $application->application_status_id = $statusId;
                $application->application_status = LsankApplication::STATUS_DRAF;
                $application->payment_status = LsankApplication::PAYMENT_BELUM_BAYAR;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan ini tidak boleh dikemaskini kerana telah dihantar untuk semakan.',
                ], 422);
            }

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

            $application->responsible_officer_name =
                $validated['responsible_officer_name'] ?? null;
            $application->responsible_officer_phone =
                $validated['responsible_officer_phone'] ?? null;
            $application->responsible_officer_position =
                $validated['responsible_officer_position'] ?? null;
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

            $application->save();

            $waterBody = LsankWaterBodyApplication::where(
                'application_id',
                $application->application_id
            )->first();

            if (!$waterBody) {
                $waterBody = new LsankWaterBodyApplication();
                $waterBody->application_id = $application->application_id;
            }

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

            return response()->json([
                'success' => true,
                'message' => $application->application_status === LsankApplication::STATUS_FI_PEMPROSESAN
                    ? 'Permohonan berjaya dikemaskini. Sila teruskan bayaran fi pemprosesan.'
                    : 'Draf permohonan badan perairan berjaya disimpan.',
                'data' => [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,
                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,
                    'status' => $application->application_status,
                    'payment_status' => $application->payment_status,
                    'current_step' => $application->current_step,
                ],
            ]);
        });
    }

    public function generateInvoice(Request $request, LsankApplication $application)
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        if ((int) $application->application_type_id !== (int) $typeId) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan badan perairan tidak dijumpai.',
            ], 404);
        }

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
            $paymentStatusId = $this->applicationStatusId('payment', 'Fi Pemprosesan', 2);

            $draftData = is_array($application->draft_data)
                ? $application->draft_data
                : [];

            $selectedActivities = $draftData['selected_activities'] ?? [];

            if (!is_array($selectedActivities) || empty($selectedActivities)) {
                $selectedActivities = [
                    $application->activity_name
                        ?? $application->activity_details
                        ?? 'Aktiviti Rekreasi Sukan Air',
                ];
            }

            $selectedActivities = collect($selectedActivities)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($selectedActivities)) {
                $selectedActivities = ['Aktiviti Rekreasi Sukan Air'];
            }

            $splitBatchId = $draftData['split_batch_id']
                ?? 'WATER-BATCH-' . $application->application_id . '-' . now()->format('YmdHis');

            $draftData['selected_activities'] = $selectedActivities;
            $draftData['processing_invoice_activities'] = $selectedActivities;
            $draftData['original_selected_activities'] = $selectedActivities;
            $draftData['split_batch_id'] = $splitBatchId;
            $draftData['is_split_parent'] = true;
            $draftData['is_split_child'] = false;

            $application->draft_data = $draftData;
            $application->application_status_id = $paymentStatusId;
            $application->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
            $application->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
            $application->submitted_at = null;
            $application->save();

            LsankInvoice::where('application_id', $application->application_id)
                ->where('status', 'unpaid')
                ->delete();

            $createdInvoices = [];
            $startInvoiceRunningNumber = $this->nextInvoiceRunningNumber();

            foreach ($selectedActivities as $index => $activity) {
                $invoice = LsankInvoice::create([
                    'application_id' => $application->application_id,
                    'user_id' => $application->user_id,
                    'invoice_no' => $this->generateInvoiceNoByRunningNumber(
                        $startInvoiceRunningNumber + $index,
                        '01'
                    ),
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'total_amount' => 150,
                    'status' => 'unpaid',
                ]);

                $createdInvoices[] = $invoice;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdInvoices) > 1
                    ? 'Invois fi pemprosesan berjaya dijana mengikut jenis aktiviti.'
                    : 'Invois fi pemprosesan berjaya dijana.',
                'data' => [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,

                    'application_ids' => [$application->application_id],

                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,

                    'application_nos' => [$application->application_ref_no],
                    'application_ref_nos' => [$application->application_ref_no],

                    'activity_names' => $selectedActivities,

                    'invoice_id' => $createdInvoices[0]->invoice_id,

                    'invoice_ids' => collect($createdInvoices)
                        ->pluck('invoice_id')
                        ->values()
                        ->all(),

                    'invoice_no' => $createdInvoices[0]->invoice_no,

                    'invoice_nos' => collect($createdInvoices)
                        ->pluck('invoice_no')
                        ->values()
                        ->all(),

                    'processing_fee' => count($createdInvoices) * 150,
                    'processing_fee_display' => 'RM ' . number_format(count($createdInvoices) * 150, 2),

                    'split_batch_id' => $splitBatchId,

                    'status' => LsankApplication::STATUS_FI_PEMPROSESAN,
                    'payment_status' => LsankApplication::PAYMENT_MENUNGGU_BAYARAN,
                ],
            ]);
        });
    }

    public function pay(Request $request, LsankApplication $application)
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        if ((int) $application->application_type_id !== (int) $typeId) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan badan perairan tidak dijumpai.',
            ], 404);
        }

        if ($application->application_status !== LsankApplication::STATUS_FI_PEMPROSESAN) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini belum berada di peringkat fi pemprosesan.',
            ], 422);
        }

        return DB::transaction(function () use ($application) {
            $statusId = $this->applicationStatusId('in_process', 'Dalam Proses', 3);

            $draftData = is_array($application->draft_data)
                ? $application->draft_data
                : [];

            $selectedActivities =
                $draftData['processing_invoice_activities']
                ?? $draftData['original_selected_activities']
                ?? $draftData['selected_activities']
                ?? [];

            if (!is_array($selectedActivities) || empty($selectedActivities)) {
                $selectedActivities = [
                    $application->activity_name
                        ?? $application->activity_details
                        ?? 'Aktiviti Rekreasi Sukan Air',
                ];
            }

            $selectedActivities = collect($selectedActivities)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($selectedActivities)) {
                $selectedActivities = ['Aktiviti Rekreasi Sukan Air'];
            }

            $splitBatchId = $draftData['split_batch_id']
                ?? 'WATER-BATCH-' . $application->application_id . '-' . now()->format('YmdHis');

            $invoices = LsankInvoice::where('application_id', $application->application_id)
                ->where('status', 'unpaid')
                ->orderBy('invoice_id')
                ->get();

            if ($invoices->count() < count($selectedActivities)) {
                LsankInvoice::where('application_id', $application->application_id)
                    ->where('status', 'unpaid')
                    ->delete();

                $invoices = collect();
                $startInvoiceRunningNumber = $this->nextInvoiceRunningNumber();

                foreach ($selectedActivities as $index => $activity) {
                    $invoices->push(
                        LsankInvoice::create([
                            'application_id' => $application->application_id,
                            'user_id' => $application->user_id,
                            'invoice_no' => $this->generateInvoiceNoByRunningNumber(
                                $startInvoiceRunningNumber + $index,
                                '01'
                            ),
                            'invoice_date' => now()->toDateString(),
                            'due_date' => now()->addDays(14)->toDateString(),
                            'total_amount' => 150,
                            'status' => 'unpaid',
                        ])
                    );
                }
            }

            $paidApplications = [];
            $paidInvoices = [];

            foreach ($selectedActivities as $index => $activity) {
                if ($index === 0) {
                    $splitApplication = $application;
                } else {
                    $splitApplication = $application->replicate();
                    $splitApplication->exists = false;
                    $splitApplication->application_id = null;
                }

                $newDraftData = $this->waterDraftDataForActivity($draftData, $activity);
                $newDraftData['selected_activities'] = [$activity];
                $newDraftData['processing_invoice_activities'] = [$activity];
                $newDraftData['original_selected_activities'] = $selectedActivities;
                $newDraftData['split_batch_id'] = $splitBatchId;
                $newDraftData['is_split_child'] = true;
                $newDraftData['is_split_parent'] = $index === 0;
                $newDraftData['split_from_application_id'] = $application->application_id;

                $splitApplication->draft_data = $newDraftData;
                $splitApplication->current_step = $application->current_step ?? 0;

                $splitApplication->activity_name = $activity;
                $splitApplication->activity_type = $activity;
                $splitApplication->activity_details = $activity;

                $splitApplication->application_ref_no = $this->generateApplicationFileNo(
                    $this->waterSectionCode($activity),
                    $this->districtCode($application->district ?? null)
                );

                $application->application_status_id = $statusId;
                $application->application_status = LsankApplication::STATUS_DALAM_PROSES;
                $application->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
                $application->submitted_at = now();
                $application->submitted_data = $application->draft_data;
                $splitApplication->application_status_id = $statusId;
                $splitApplication->application_status = LsankApplication::STATUS_DALAM_PROSES;
                $splitApplication->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
                $splitApplication->submitted_at = now();

                $splitApplication->remarks = trim(
                    (($splitApplication->remarks ?? '') . "\nBayaran simulasi berjaya pada " . now()->format('d/m/Y H:i'))
                );

                $application->save();

                $this->syncWaterBodyForActivity($application, $activity);

                $createdApplications[] = $application;
            } else {
                foreach ($selectedActivities as $activity) {
                    $newApplication = $application->replicate();

                    $newApplication->application_ref_no = $this->generateApplicationFileNo(
                        $this->waterSectionCode($activity),
                        $this->districtCode($application->district ?? null)
                    );

                    $newApplication->application_status_id = $statusId;
                    $newApplication->application_status = LsankApplication::STATUS_DALAM_PROSES;
                    $newApplication->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
                    $newApplication->submitted_at = now();

                    $newApplication->activity_name = $activity;
                    $newApplication->activity_type = $activity;
                    $newApplication->activity_details = $activity;

                    $newDraftData = $this->waterDraftDataForActivity($draftData, $activity);
                    $newDraftData['selected_activities'] = [$activity];
                    $newDraftData['processing_invoice_activities'] = $selectedActivities;
                    $newDraftData['original_selected_activities'] = $selectedActivities;
                    $newDraftData['split_batch_id'] = $splitBatchId;
                    $newDraftData['is_split_child'] = true;
                    $newDraftData['split_from_application_id'] = $application->application_id;

                    $newApplication->draft_data = $newDraftData;
                    $newApplication->submitted_data = $newDraftData;
                    $newApplication->current_step = $application->current_step ?? 0;

                    $newApplication->remarks = trim(
                        (($newApplication->remarks ?? '') . "\nBayaran simulasi berjaya pada " . now()->format('d/m/Y H:i'))
                    );

                    $newApplication->save();

                    $this->syncWaterBodyForActivity($newApplication, $activity);

                    $createdApplications[] = $newApplication;
                $splitApplication->save();

                $this->syncWaterBodyForActivity($splitApplication, $activity);

                $invoice = $invoices->values()->get($index);

                if (!$invoice) {
                    $invoice = LsankInvoice::create([
                        'application_id' => $splitApplication->application_id,
                        'user_id' => $splitApplication->user_id,
                        'invoice_no' => $this->generateInvoiceNoByRunningNumber(
                            $this->nextInvoiceRunningNumber(),
                            '01'
                        ),
                        'invoice_date' => now()->toDateString(),
                        'due_date' => now()->addDays(14)->toDateString(),
                        'total_amount' => 150,
                        'status' => 'unpaid',
                    ]);
                }

                $invoice->application_id = $splitApplication->application_id;
                $invoice->user_id = $splitApplication->user_id;
                $invoice->total_amount = 150;
                $invoice->status = 'paid';
                $invoice->save();

                $paidApplications[] = $splitApplication;
                $paidInvoices[] = $invoice;
            }

            $firstApplication = $paidApplications[0];
            $firstInvoice = $paidInvoices[0];

            $receiptNos = collect($paidApplications)
                ->map(function ($item) {
                    $year = now()->format('Y');
                    $runningNo = str_pad($item->application_id, 4, '0', STR_PAD_LEFT);

                    return 'RESIT-' . $year . '-' . $runningNo . '-01';
                })
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'message' => count($paidApplications) > 1
                    ? 'Bayaran berjaya. Permohonan telah dipecahkan mengikut aktiviti dan dihantar untuk semakan.'
                    : 'Bayaran berjaya. Permohonan telah dihantar untuk semakan.',
                'data' => [
                    'id' => $firstApplication->application_id,
                    'application_id' => $firstApplication->application_id,

                    'application_ids' => collect($paidApplications)
                        ->pluck('application_id')
                        ->values()
                        ->all(),

                    'application_no' => $firstApplication->application_ref_no,
                    'application_ref_no' => $firstApplication->application_ref_no,

                    'application_nos' => collect($paidApplications)
                        ->pluck('application_ref_no')
                        ->values()
                        ->all(),

                    'application_ref_nos' => collect($paidApplications)
                        ->pluck('application_ref_no')
                        ->values()
                        ->all(),

                    'activity_names' => collect($paidApplications)
                        ->pluck('activity_name')
                        ->values()
                        ->all(),

                    'invoice_id' => $firstInvoice->invoice_id,

                    'invoice_ids' => collect($paidInvoices)
                        ->pluck('invoice_id')
                        ->values()
                        ->all(),

                    'invoice_no' => $firstInvoice->invoice_no,

                    'invoice_nos' => collect($paidInvoices)
                        ->pluck('invoice_no')
                        ->values()
                        ->all(),

                    'receipt_id' => $firstApplication->application_id,

                    'receipt_ids' => collect($paidApplications)
                        ->pluck('application_id')
                        ->values()
                        ->all(),

                    'receipt_no' => $receiptNos[0] ?? null,
                    'receipt_nos' => $receiptNos,

                    'split_batch_id' => $splitBatchId,

                    'status' => LsankApplication::STATUS_DALAM_PROSES,
                    'status_display' => 'Dalam Proses',
                    'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                    'payment_status_display' => 'Sudah Bayar',
                    'paid_at' => now()->toDateTimeString(),
                ],
            ]);
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant_type' => ['nullable', 'string', 'max:100'],
            'applicant_name' => ['required', 'string', 'max:255'],
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
        ]);

        $user = $request->user();
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
        $statusId = $this->applicationStatusId('in_process', 'Dalam Proses', 3);
        $phoneColumn = $this->applicantPhoneColumn();

        return DB::transaction(function () use (
            $validated,
            $user,
            $typeId,
            $statusId,
            $phoneColumn
        ) {
            $applicantData = [
                'user_id' => $user->user_id,
                'applicant_type' => $this->normalizeApplicantType(
                    $validated['applicant_type'] ?? null
                ),
                'applicant_name' => $validated['applicant_name'],
                'identity_no' => $validated['identity_no'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => 'active',
            ];

            $applicantData[$phoneColumn] =
                $validated['phone_no'] ??
                $validated['phone'] ??
                null;

            $applicant = LsankApplicant::create($applicantData);

            if (!empty($validated['company_name'])) {
                LsankCompany::create([
                    'applicant_id' => $applicant->applicant_id,
                    'company_name' => $validated['company_name'],
                    'registration_no' => $validated['registration_no'] ?? null,
                    'business_address' => $validated['business_address'] ?? null,
                    'business_phone' => $validated['business_phone'] ?? null,
                    'business_email' => $validated['business_email'] ?? null,
                    'responsible_officer_name' =>
                        $validated['responsible_officer_name'] ?? null,
                    'responsible_officer_phone' =>
                        $validated['responsible_officer_phone'] ?? null,
                ]);
            }

            $application = LsankApplication::create([
                'application_ref_no' => $this->generateApplicationFileNo(
                    $this->waterSectionCode($validated['activity_name'] ?? null),
                    $this->districtCode($validated['district'] ?? null)
                ),
                'user_id' => $user->user_id,
                'applicant_id' => $applicant->applicant_id,
                'application_type_id' => $typeId,
                'application_status_id' => $statusId,
                'application_category' => 'new',
                'submitted_at' => now(),
                'remarks' => null,

                'license_type' => self::TYPE_NAME,
                'activity_type' => $validated['activity_name'] ?? null,
                'application_type' => 'water',
                'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                'application_status' => LsankApplication::STATUS_DALAM_PROSES,

                'applicant_name' => $validated['applicant_name'],
                'business_name' => $validated['company_name'] ?? null,
                'phone' => $validated['phone_no'] ?? $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,

                'applicant_type' => $validated['applicant_type'] ?? null,
                'identity_no' => $validated['identity_no'] ?? null,
                'phone_no' => $validated['phone_no'] ?? $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,

                'company_name' => $validated['company_name'] ?? null,
                'registration_no' => $validated['registration_no'] ?? null,
                'business_address' => $validated['business_address'] ?? null,
                'business_phone' => $validated['business_phone'] ?? null,
                'business_email' => $validated['business_email'] ?? null,

                'responsible_officer_name' =>
                    $validated['responsible_officer_name'] ?? null,
                'responsible_officer_phone' =>
                    $validated['responsible_officer_phone'] ?? null,
                'responsible_officer_position' =>
                    $validated['responsible_officer_position'] ?? null,
                'officers' => $validated['officers'] ?? [],

                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'activity_name' => $validated['activity_name'] ?? null,
                'district' => $validated['district'] ?? null,
                'activity_location' => $validated['activity_location'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'operating_days' => $validated['operating_days'] ?? null,
                'operating_time' => $validated['operating_time'] ?? null,
                'activity_details' => $validated['activity_details'] ?? null,
                'recreation_details' => $validated['recreation_details'] ?? [],
                'current_step' => $validated['current_step'] ?? 0,
                'draft_data' => $this->normalizeWaterDraftData($validated, request()),
            ]);

            LsankWaterBodyApplication::create([
                'application_id' => $application->application_id,
                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'activity_location' => $validated['activity_location'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'operating_days' => $validated['operating_days'] ?? null,
                'operating_time' => $validated['operating_time'] ?? null,
                'motorized_fee' => $validated['motorized_fee'] ?? 0,
                'non_motorized_fee' => $validated['non_motorized_fee'] ?? 0,
                'activity_details' => $validated['activity_details'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permohonan badan perairan berjaya dihantar.',
                'data' => [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,
                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,
                    'status' => $application->application_status,
                    'payment_status' => $application->payment_status,
                ],
            ], 201);
        });
    }

    public function show(Request $request, LsankApplication $application)
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            abort(403, 'Anda tidak dibenarkan melihat permohonan ini.');
        }

        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        if ((int) $application->application_type_id !== (int) $typeId) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan badan perairan tidak dijumpai.',
            ], 404);
        }

        $application->load([
            'user',
            'applicant.company',
            'status',
            'type',
            'waterBody',
            'documents',
            'reviews',
        ]);

        $detail = $this->formatApplicationDetail(
            $application,
            self::TYPE_NAME
        );

        $detail['id'] = $application->application_id;
        $detail['application_id'] = $application->application_id;
        $detail['application_no'] = $application->application_ref_no;
        $detail['application_ref_no'] = $application->application_ref_no;

        $detail['current_step'] = $application->current_step ?? 0;
        $detail['draft_data'] = $application->draft_data ?? [];
        $detail['review_data'] = $application->review_data ?? [];
        $detail['submitted_data'] = $application->submitted_data ?? [];

        $detail['applicant_type'] = $application->applicant_type;
        $detail['applicant_name'] = $application->applicant_name;
        $detail['identity_no'] = $application->identity_no;
        $detail['email'] = $application->email;
        $detail['phone_no'] = $application->phone_no;
        $detail['phone'] = $application->phone;
        $detail['address'] = $application->address;

        $detail['company_name'] = $application->company_name;
        $detail['business_name'] = $application->business_name;
        $detail['registration_no'] = $application->registration_no;
        $detail['business_address'] = $application->business_address;
        $detail['business_phone'] = $application->business_phone;
        $detail['business_email'] = $application->business_email;

        $detail['responsible_officer_name'] = $application->responsible_officer_name;
        $detail['responsible_officer_phone'] = $application->responsible_officer_phone;
        $detail['responsible_officer_position'] = $application->responsible_officer_position;
        $detail['officers'] = $application->officers ?? [];

        $detail['activity_name'] = $application->activity_name;
        $detail['activity_details'] = $application->activity_details;
        $detail['district'] = $application->district;
        $detail['activity_location'] = $application->activity_location;
        $detail['longitude'] = $application->longitude;
        $detail['latitude'] = $application->latitude;
        $detail['operating_days'] = $application->operating_days;
        $detail['operating_time'] = $application->operating_time;
        $detail['recreation_details'] = $application->recreation_details ?? [];

        $draftData = is_array($application->draft_data)
            ? $application->draft_data
            : [];

        $splitBatchId = $draftData['split_batch_id'] ?? null;

        $invoiceApplicationIds = [$application->application_id];

        if ($splitBatchId) {
            $sameBatchApplicationIds = LsankApplication::where('user_id', $application->user_id)
                ->where('application_type_id', $application->application_type_id)
                ->where('application_status', LsankApplication::STATUS_DALAM_PROSES)
                ->where('draft_data->split_batch_id', $splitBatchId)
                ->pluck('application_id')
                ->values()
                ->all();

            $invoiceApplicationIds = array_values(array_unique(array_merge(
                $invoiceApplicationIds,
                $sameBatchApplicationIds
            )));
        }

        $invoiceItems = LsankInvoice::with('application')
            ->whereIn('application_id', $invoiceApplicationIds)
            ->orderBy('invoice_id')
            ->get()
            ->map(function ($invoice) {
                $invoiceApplication = $invoice->application;

                $applicationRefNo = $invoiceApplication?->application_ref_no;
                $activityName = $invoiceApplication?->activity_name
                    ?? $invoiceApplication?->activity_details;

                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_no' => $invoice->invoice_no,
                    'payment_type' => $invoice->payment_type ?? 'Fi Pemprosesan',
                    'amount' => (float) $invoice->total_amount,
                    'amount_display' => 'RM ' . number_format($invoice->total_amount, 2),
                    'invoice_date' => optional($invoice->invoice_date)->format('d/m/Y') ?? '-',
                    'due_date' => optional($invoice->due_date)->format('d/m/Y') ?? '-',
                    'status' => $invoice->status,
                    'paid' => $invoice->status === 'paid',

                    'application_id' => $invoice->application_id,
                    'application_ref_no' => $applicationRefNo,
                    'application_no' => $applicationRefNo,
                    'application_ref_nos' => $applicationRefNo ? [$applicationRefNo] : [],
                    'application_nos' => $applicationRefNo ? [$applicationRefNo] : [],

                    'activity_name' => $activityName,
                    'activity_details' => $activityName,
                ];
            })
            ->values()
            ->all();

        $processingInvoiceActivities =
            $draftData['processing_invoice_activities']
            ?? $draftData['original_selected_activities']
            ?? $draftData['selected_activities']
            ?? [$application->activity_name ?? $application->activity_details ?? '-'];

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

        $detail['invoice_items'] = $invoiceItems;
        $detail['processing_invoice_activities'] = $processingInvoiceActivities;
        $detail['application_ref_nos'] = $applicationRefNos;
        $detail['application_nos'] = $applicationRefNos;

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function destroyDraft(Request $request, LsankApplication $application)
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        if ((int) $application->application_type_id !== (int) $typeId) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan badan perairan tidak dijumpai.',
            ], 404);
        }

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
            LsankInvoice::where('application_id', $application->application_id)
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

    private function normalizeWaterDraftData(array $validated, Request $request, ?LsankApplication $application = null): array
    {
        $incoming = $validated['draft_data'] ?? $request->input('draft_data') ?? [];

        if (!is_array($incoming)) {
            $incoming = [];
        }

        $existing = ($application && is_array($application->draft_data))
            ? $application->draft_data
            : [];

        $draftData = array_replace_recursive($existing, $incoming);

        $selectedActivities = $draftData['selected_activities']
            ?? ($draftData['meta']['selected_activities'] ?? null)
            ?? $this->normalizeStringList($validated['activity_details'] ?? null);

        if (!is_array($selectedActivities) || empty($selectedActivities)) {
            $selectedActivities = [$validated['activity_name'] ?? 'Aktiviti Rekreasi Sukan Air'];
        }

        $selectedActivities = collect($selectedActivities)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $controllers = $draftData['controllers'] ?? [];
        if (!is_array($controllers)) {
            $controllers = [];
        }

        $recreationDetails = $draftData['recreation_details']
            ?? ($draftData['borang_c']['recreation_details'] ?? ($validated['recreation_details'] ?? []));

        $vesselDetails = $draftData['vessel_details']
            ?? ($draftData['borang_d']['vessel_details'] ?? []);

        $cageDetails = $draftData['cage_details']
            ?? ($draftData['borang_f'] ?? []);

        $constructionDetails = $draftData['construction_details']
            ?? ($draftData['borang_g'] ?? []);

        $licenseDurationYear = $draftData['license_duration_year']
            ?? ($draftData['meta']['license_duration_year'] ?? 1);

        $draftData['meta'] = array_replace_recursive($draftData['meta'] ?? [], [
            'module' => 'water',
            'step' => $validated['current_step'] ?? ($draftData['step'] ?? ($draftData['meta']['step'] ?? 0)),
            'current_step' => $validated['current_step'] ?? ($draftData['current_step'] ?? ($draftData['meta']['current_step'] ?? 0)),
            'selected_activities' => $selectedActivities,
            'applicant_type' => $validated['applicant_type'] ?? ($draftData['applicant_type'] ?? null),
            'is_one_off' => $draftData['is_one_off'] ?? ($draftData['meta']['is_one_off'] ?? false),
            'license_duration_year' => $licenseDurationYear,
        ]);

        $draftData['selected_activities'] = $selectedActivities;
        $draftData['recreation_details'] = is_array($recreationDetails) ? $recreationDetails : [];
        $draftData['vessel_details'] = is_array($vesselDetails) ? $vesselDetails : [];
        $draftData['cage_details'] = is_array($cageDetails) ? $cageDetails : [];
        $draftData['construction_details'] = is_array($constructionDetails) ? $constructionDetails : [];

        $draftData['borang_a'] = array_replace_recursive($draftData['borang_a'] ?? [], [
            'pemohon' => [
                'applicant_type' => $validated['applicant_type'] ?? null,
                'applicant_name' => $validated['applicant_name'] ?? null,
                'identity_no' => $validated['identity_no'] ?? null,
                'phone' => $validated['phone_no'] ?? $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
            ],
            'perniagaan' => [
                'business_name' => $validated['company_name'] ?? null,
                'registration_no' => $validated['registration_no'] ?? null,
                'business_address' => $validated['business_address'] ?? null,
                'business_phone' => $validated['business_phone'] ?? null,
                'business_email' => $validated['business_email'] ?? null,
                'district' => $validated['district'] ?? null,
            ],
            'officers' => $validated['officers'] ?? ($draftData['officers'] ?? []),
        ]);

        $draftData['borang_c'] = array_replace_recursive($draftData['borang_c'] ?? [], [
            'recreation_details' => $draftData['recreation_details'],
            'location' => [
                'search' => $controllers['borang_c_location_search'] ?? $validated['activity_location'] ?? null,
                'longitude' => $controllers['borang_c_longitude'] ?? $validated['longitude'] ?? null,
                'latitude' => $controllers['borang_c_latitude'] ?? $validated['latitude'] ?? null,
            ],
            'operation' => [
                'days' => $controllers['borang_c_operating_days'] ?? $validated['operating_days'] ?? null,
                'start_time' => $controllers['borang_c_start_time'] ?? null,
                'end_time' => $controllers['borang_c_end_time'] ?? null,
            ],
        ]);

        $draftData['borang_d'] = array_replace_recursive($draftData['borang_d'] ?? [], [
            'vessel_details' => $draftData['vessel_details'],
            'vessel_types_by_index' => $draftData['vessel_types_by_index'] ?? [],
            'location' => [
                'search' => $controllers['borang_d_location_search'] ?? null,
                'longitude' => $controllers['borang_d_longitude'] ?? null,
                'latitude' => $controllers['borang_d_latitude'] ?? null,
            ],
            'operation' => [
                'days' => $controllers['borang_d_operating_days'] ?? null,
                'start_time' => $controllers['borang_d_start_time'] ?? null,
                'end_time' => $controllers['borang_d_end_time'] ?? null,
                'note' => $controllers['borang_d_operation_note'] ?? null,
            ],
        ]);

        $draftData['borang_f'] = $draftData['cage_details'];
        $draftData['borang_g'] = $draftData['construction_details'];
        $draftData['documents'] = array_replace_recursive($draftData['documents'] ?? [], [
            'uploaded_keys' => $draftData['uploaded_documents'] ?? ($draftData['documents']['uploaded_keys'] ?? []),
        ]);
        $draftData['controllers'] = $controllers;

        return $draftData;
    }

    private function normalizeStringList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/[,;\/]/', $value))
            ->map(fn ($item) => trim((string) $item))
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
            if (isset($draftData['borang_c'])) {
                $draftData['borang_c'] = [];
            }
        }

        if ($activity !== 'Aktiviti Vesel Rekreasi') {
            $draftData['vessel_details'] = [];
            $draftData['vessel_types_by_index'] = [];
            if (isset($draftData['borang_d'])) {
                $draftData['borang_d'] = [];
            }
        }

        if ($activity !== 'Aktiviti Sangkar') {
            $draftData['cage_details'] = [];
            if (isset($draftData['borang_f'])) {
                $draftData['borang_f'] = [];
            }
        }

        if ($activity !== 'Aktiviti Binaan') {
            $draftData['construction_details'] = [];
            if (isset($draftData['borang_g'])) {
                $draftData['borang_g'] = [];
            }
        }

        return $draftData;
    }

    private function syncWaterBodyForActivity(LsankApplication $application, string $activity): void
    {
        $waterBody = LsankWaterBodyApplication::where(
            'application_id',
            $application->application_id
        )->first();

        if (!$waterBody) {
            $waterBody = new LsankWaterBodyApplication();
            $waterBody->application_id = $application->application_id;
        }

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
        $draftData = is_array($application->draft_data)
            ? $application->draft_data
            : [];

        $meta = is_array($draftData['meta'] ?? null) ? $draftData['meta'] : [];

        $isOneOff = (($draftData['is_one_off'] ?? $meta['is_one_off'] ?? false) === true);

        $selectedActivities = $draftData['selected_activities']
            ?? $meta['selected_activities']
            ?? [];

        if (!is_array($selectedActivities)) {
            $selectedActivities = [];
        }

        $licenseDurationYear = (int) ($draftData['license_duration_year'] ?? $meta['license_duration_year'] ?? 1);

        if ($licenseDurationYear < 1) {
            $licenseDurationYear = 1;
        }

        $hasRecreation = in_array('Aktiviti Rekreasi Sukan Air', $selectedActivities, true);
        $hasVessel = in_array('Aktiviti Vesel Rekreasi', $selectedActivities, true);
        $hasCage = in_array('Aktiviti Sangkar', $selectedActivities, true);
        $hasConstruction = in_array('Aktiviti Binaan', $selectedActivities, true);

        $licenseActivityCount = 0;

        if ($hasRecreation) {
            $licenseActivityCount++;
        }

        if ($hasVessel) {
            $licenseActivityCount++;
        }

        if ($hasCage) {
            $licenseActivityCount++;
        }

        if ($hasConstruction) {
            $licenseActivityCount++;
        }

        if ($licenseActivityCount < 1) {
            $licenseActivityCount = 1;
        }

        $processingFee = 150 * $licenseActivityCount;
        $securityFee = 0;
        $licenseFee = 0;
        $chargeFee = 0;
        $chargeItems = [];

        $constructionType = '';
        $mooringCount = 0;

        if ($isOneOff) {
            if ($hasConstruction) {
                $securityFee += 1000;
            }
        } else {
            if ($hasRecreation) {
                $securityFee += 1000;
            }

            if ($hasVessel) {
                $securityFee += 1000;
            }

            if ($hasCage) {
                $securityFee += 1000;
            }

            if ($hasConstruction) {
                $securityFee += 1000;
            }
        }

        if ($isOneOff) {
            $licenseFee = 250 * $licenseActivityCount;
        } else {
            $licenseFee = 500 * $licenseDurationYear * $licenseActivityCount;
        }

        if ($hasRecreation) {
            $recreationDetails = $draftData['recreation_details']
                ?? ($draftData['borang_c']['recreation_details'] ?? []);

            if (is_array($recreationDetails)) {
                foreach ($recreationDetails as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $activity = trim((string) ($item['activity'] ?? 'Aktiviti Rekreasi'));
                    $type = trim((string) ($item['type'] ?? ''));
                    $quantity = (int) ($item['quantity'] ?? 0);

                    if ($quantity < 1 || $type === '') {
                        continue;
                    }

                    $isNonMotor = str_contains(strtolower($type), 'tidak');
                    $rate = $isNonMotor ? 10 : 50;
                    $amount = $rate * $quantity;

                    $chargeFee += $amount;

                    $chargeItems[] = [
                        'title' => 'Aktiviti Rekreasi Sukan Air - ' . $activity,
                        'description' => $isNonMotor
                            ? 'Tidak bermotor: RM10.00 x ' . $quantity . ' unit setahun'
                            : 'Bermotor: RM50.00 x ' . $quantity . ' unit setahun',
                        'amount' => $amount,
                        'amount_display' => 'RM ' . number_format($amount, 2),
                    ];
                }
            }
        }

        if ($hasVessel) {
            $vesselDetails = $draftData['vessel_details']
                ?? ($draftData['borang_d']['vessel_details'] ?? []);

            if (is_array($vesselDetails)) {
                foreach ($vesselDetails as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $type = trim((string) ($item['type'] ?? 'Vesel'));
                    $passengerCount = (int) ($item['passenger_count'] ?? 0);

                    if ($type === '') {
                        continue;
                    }

                    $isTenderBoat = str_contains(strtolower($type), 'tender');

                    if ($isTenderBoat) {
                        $amount = 100;
                        $chargeFee += $amount;

                        $chargeItems[] = [
                            'title' => 'Aktiviti Vesel Rekreasi - ' . $type,
                            'description' => 'Tender Boat: RM100.00 per tender',
                            'amount' => $amount,
                            'amount_display' => 'RM ' . number_format($amount, 2),
                        ];

                        continue;
                    }

                    if ($passengerCount < 1) {
                        continue;
                    }

                    $amount = $passengerCount <= 12 ? 100 : 200;
                    $chargeFee += $amount;

                    $chargeItems[] = [
                        'title' => 'Aktiviti Vesel Rekreasi - ' . $type,
                        'description' => $passengerCount <= 12
                            ? 'Bermotor penumpang tidak melebihi 12 orang: RM100.00 seunit setahun'
                            : 'Bermotor penumpang melebihi 12 orang dan ke atas: RM200.00 seunit setahun',
                        'amount' => $amount,
                        'amount_display' => 'RM ' . number_format($amount, 2),
                    ];
                }
            }
        }

        if ($hasCage) {
            $cageDetails = $draftData['cage_details']
                ?? ($draftData['borang_f'] ?? []);
            $area = 0;

            if (is_array($cageDetails)) {
                $area = (float) ($cageDetails['cage_area'] ?? 0);
            }

            if ($area > 0 && $area <= 200) {
                $chargeItems[] = [
                    'title' => 'Aktiviti Sangkar',
                    'description' => 'Keluasan kurang daripada 200 meter persegi: Dikecualikan',
                    'amount' => 0,
                    'amount_display' => 'RM 0.00',
                ];
            }

            if ($area > 200) {
                $amount = $area * 1;
                $chargeFee += $amount;

                $chargeItems[] = [
                    'title' => 'Aktiviti Sangkar',
                    'description' => 'Keluasan melebihi 200 meter persegi: RM1.00 x ' . number_format($area, 0) . ' meter persegi',
                    'amount' => $amount,
                    'amount_display' => 'RM ' . number_format($amount, 2),
                ];
            }
        }

        if ($hasConstruction) {
            $constructionDetails = $draftData['construction_details']
                ?? ($draftData['borang_g'] ?? []);
            $area = 0;

            if (is_array($constructionDetails)) {
                $area = (float) ($constructionDetails['construction_area'] ?? 0);
                $constructionType = strtolower(trim((string) ($constructionDetails['construction_type'] ?? '')));
                $mooringCount = (int) ($constructionDetails['mooring_count'] ?? 0);
            }

            if ($area > 0) {
                if ($area <= 200) {
                    $amount = 200;
                    $description = 'Apa-apa jenis binaan: RM1.00 per meter persegi pertama tertakluk kepada kadar minimum RM200.00';
                } else {
                    $amount = 200 + (($area - 200) * 2);
                    $description = 'Apa-apa jenis binaan: 200 meter persegi pertama minimum RM200.00 + baki ' . number_format($area - 200, 0) . ' meter persegi x RM2.00';
                }

                $chargeFee += $amount;

                $chargeItems[] = [
                    'title' => 'Aktiviti Binaan',
                    'description' => $description,
                    'amount' => $amount,
                    'amount_display' => 'RM ' . number_format($amount, 2),
                ];
            }

            if ($constructionType === 'jeti' && $mooringCount > 0) {
                $amount = $mooringCount * 100;
                $chargeFee += $amount;

                $chargeItems[] = [
                    'title' => 'Tambatan Vesel',
                    'description' => 'Tambatan vesel: RM100.00 x ' . $mooringCount . ' unit',
                    'amount' => $amount,
                    'amount_display' => 'RM ' . number_format($amount, 2),
                ];
            }
        }

        return [
            'processing_fee' => $processingFee,
            'security_fee' => $securityFee,
            'license_fee' => $licenseFee,
            'charge_fee' => $chargeFee,
            'charge_items' => $chargeItems,
            'is_one_off' => $isOneOff,
            'license_duration_year' => $licenseDurationYear,
            'license_activity_count' => $licenseActivityCount,
            'total_after_approval' => $securityFee + $licenseFee + $chargeFee,
        ];
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

    private function generateProcessingInvoiceNo(LsankApplication $application): string
    {
        return $this->generateInvoiceNoByRunningNumber(
            $this->nextInvoiceRunningNumber(),
            '01'
        );
    }

    private function generateProcessingInvoiceNoByIndex(LsankApplication $application, int $index): string
    {
        return $this->generateInvoiceNoByRunningNumber(
            $this->nextInvoiceRunningNumber() + $index,
            '01'
        );
    }

    private function generateInvoiceNoByRunningNumber(int $runningNumber, string $feeTypeCode): string
    {
        $year = now()->format('Y');
        $runningNo = str_pad($runningNumber, 4, '0', STR_PAD_LEFT);

        return 'INVOIS-' . $year . '-' . $runningNo . '-' . $feeTypeCode;
    }

    private function nextInvoiceRunningNumber(): int
    {
        $year = now()->format('Y');

        $latestInvoice = LsankInvoice::where('invoice_no', 'like', 'INVOIS-' . $year . '-%')
            ->orderByDesc('invoice_id')
            ->first();

        if (!$latestInvoice || empty($latestInvoice->invoice_no)) {
            return 101;
        }

        $parts = explode('-', $latestInvoice->invoice_no);

        if (count($parts) < 3) {
            return 101;
        }

        $latestRunningNo = (int) $parts[2];

        if ($latestRunningNo < 101) {
            return 101;
        }

        return $latestRunningNo + 1;
    }

    private function createOrUpdateProcessingInvoice(
        LsankApplication $application,
        array $fees = []
    ): LsankInvoice {
        return LsankInvoice::updateOrCreate(
            [
                'application_id' => $application->application_id,
                'invoice_no' => $this->generateProcessingInvoiceNo($application),
            ],
            [
                'user_id' => $application->user_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'total_amount' => 150,
                'status' => 'unpaid',
            ]
        );
    }

    private function generateApplicationFileNo(string $sectionCode, string $districtCode): string
    {
        $runningNumber = $this->nextApplicationFileRunningNumber(
            $sectionCode,
            $districtCode
        );

        return $sectionCode . '/' . $districtCode . '/' . str_pad($runningNumber, 4, '0', STR_PAD_LEFT);
    }

    private function nextApplicationFileRunningNumber(string $sectionCode, string $districtCode): int
    {
        $prefix = $sectionCode . '/' . $districtCode . '/';

        $latestApplication = LsankApplication::where('application_ref_no', 'like', $prefix . '%')
            ->orderByDesc('application_id')
            ->first();

        if (!$latestApplication || empty($latestApplication->application_ref_no)) {
            return 1;
        }

        $parts = explode('/', $latestApplication->application_ref_no);

        if (count($parts) < 3) {
            return 1;
        }

        return ((int) $parts[2]) + 1;
    }

    private function waterSectionCode(?string $activity): string
    {
        $value = strtolower(trim((string) $activity));

        if (str_contains($value, 'rekreasi sukan air')) {
            return '600-15';
        }

        if (str_contains($value, 'vesel rekreasi')) {
            return '600-16';
        }

        if (str_contains($value, 'sangkar')) {
            return '600-18';
        }

        if (str_contains($value, 'binaan')) {
            return '600-19';
        }

        return '600-15';
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

    private function generateDraftReferenceNo(int $userId): string
    {
        do {
            $refNo = 'DRAF-' . $userId . '-' . now()->format('YmdHis');
        } while (
            LsankApplication::where('application_ref_no', $refNo)->exists()
        );

        return $refNo;
    }

}