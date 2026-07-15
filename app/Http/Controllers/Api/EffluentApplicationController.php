<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesApplicationData;
use App\Http\Controllers\Controller;
use App\Models\LsankApplicant;
use App\Models\LsankApplication;
use App\Models\LsankCompany;
use App\Models\LsankCompanyOfficer;
use App\Models\LsankEffluentApplication;
use App\Models\LsankServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LsankInvoice;

class EffluentApplicationController extends Controller
{
    use HandlesApplicationData;

    private const TYPE_CODE = 'EFFLUENT';
    private const TYPE_NAME = 'Aktiviti Pelepasan Efluen';

    public function index(Request $request)
{
    $typeId = $this->applicationTypeId(
        self::TYPE_CODE,
        self::TYPE_NAME
    );

    $applications = LsankApplication::with([
            'applicant.company.officers',
            'status',
            'type',
            'effluent.serviceType',
        ])
        ->where('user_id', $request->user()->user_id)
        ->where('application_type_id', $typeId)
        ->latest('application_id')
        ->get()
        ->map(function ($application) {
            $item = $this->formatApplicationListItem(
                $application,
                self::TYPE_NAME
            );

            $serviceName =
                $application->effluent?->serviceType?->service_name
                ?? '-';

            $invoiceItems = LsankInvoice::where(
                    'application_id',
                    $application->application_id
                )
                ->orderBy('invoice_id')
                ->get()
                ->map(function ($invoice) use (
                    $application,
                    $serviceName
                ) {
                    return [
                        'invoice_id' => $invoice->invoice_id,
                        'application_id' =>
                            $application->application_id,

                        'invoice_no' => $invoice->invoice_no,
                        'payment_type' => 'Fi Pemprosesan',

                        'amount' => (float) $invoice->total_amount,
                        'amount_display' => 'RM ' . number_format(
                            (float) $invoice->total_amount,
                            2
                        ),

                        'invoice_date' => optional(
                            $invoice->invoice_date
                        )->format('d/m/Y') ?? '-',

                        'due_date' => optional(
                            $invoice->due_date
                        )->format('d/m/Y') ?? '-',

                        'status' => $invoice->status,
                        'paid' => $invoice->status === 'paid',

                        'application_ref_no' =>
                            $application->application_ref_no,
                        'application_no' =>
                            $application->application_ref_no,

                        'service_name' => $serviceName,
                        'activity_name' => $serviceName,
                        'activity_details' => $serviceName,
                    ];
                })
                ->values()
                ->all();

            $receiptItems = [];

            if (
                $application->payment_status ===
                    LsankApplication::PAYMENT_SUDAH_BAYAR
            ) {
                $year = optional($application->updated_at)
                    ->format('Y') ?? now()->format('Y');

                $runningNo = str_pad(
                    (string) $application->application_id,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

                $paidInvoice = collect($invoiceItems)
                    ->firstWhere('paid', true);

                $receiptItems[] = [
                    'receipt_id' =>
                        $application->application_id,

                    'application_id' =>
                        $application->application_id,

                    'receipt_no' =>
                        'RESIT-' . $year . '-' .
                        $runningNo . '-01',

                    'invoice_id' =>
                        $paidInvoice['invoice_id'] ?? null,

                    'invoice_no' =>
                        $paidInvoice['invoice_no'] ?? null,

                    'application_ref_no' =>
                        $application->application_ref_no,

                    'application_no' =>
                        $application->application_ref_no,

                    'payment_type' => 'Fi Pemprosesan',

                    'amount' => (float) (
                        $paidInvoice['amount'] ?? 150
                    ),

                    'amount_display' =>
                        $paidInvoice['amount_display']
                        ?? 'RM 150.00',

                    'paid_date' => optional(
                        $application->updated_at
                    )->format('d/m/Y') ?? '-',

                    'paid_at' => optional(
                        $application->updated_at
                    )->toDateTimeString(),

                    'service_name' => $serviceName,
                    'activity_name' => $serviceName,
                    'activity_details' => $serviceName,
                ];
            }

            $item['applicant_name'] =
                $application->applicant_name
                ?? $application->applicant?->applicant_name
                ?? '-';

            $item['business_name'] =
                $application->business_name
                ?? $application->company_name
                ?? $application->applicant?->company?->company_name
                ?? '-';

            $item['company_name'] =
                $application->company_name
                ?? $application->applicant?->company?->company_name
                ?? '-';

            $item['email'] =
                $application->email
                ?? $application->applicant?->email
                ?? '-';

            $item['phone'] =
                $application->phone
                ?? $application->phone_no
                ?? '-';

            $item['application_type_source'] = 'effluent';

            $item['service_name'] = $serviceName;
            $item['activity_name'] = $serviceName;
            $item['activity_details'] = $serviceName;

            $item['payment_status'] =
                $application->payment_status;

            $item['invoice_items'] = $invoiceItems;
            $item['receipt_items'] = $receiptItems;

            return $item;
        });

    return response()->json([
        'success' => true,
        'data' => $applications,
    ]);
}

    public function store(Request $request)
    {
        return $this->saveDraft($request);
    }

    public function saveStep(Request $request)
    {
        return $this->saveDraft($request);
    }

    public function saveDraft(Request $request)
    {
        $validated = $request->validate([
            'application_id' => ['nullable', 'integer', 'exists:lsank_applications,application_id'],

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

            'service_type_id' => ['nullable', 'integer', 'exists:lsank_service_types,service_type_id'],
            'district' => ['nullable', 'string', 'max:100'],
            'activity_location' => ['nullable', 'string'],
            'longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'composition' => ['nullable', 'string'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'flow_rate' => ['nullable', 'string', 'max:255'],
            'sampling_method' => ['nullable', 'string'],
            'contingency_plan' => ['nullable', 'string'],
            'disposal_method' => ['nullable', 'string'],

            'current_step' => ['nullable', 'integer'],
            'draft_data' => ['nullable', 'array'],
            'submitted_data' => ['nullable', 'array'],
            'officers' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
        $statusId = $this->applicationStatusId('draft', 'Draf', 1);
        $phoneColumn = $this->applicantPhoneColumn();

        return DB::transaction(function () use ($validated, $user, $typeId, $statusId, $phoneColumn) {
            $application = null;

            if (!empty($validated['application_id'])) {
                $application = LsankApplication::where('application_id', $validated['application_id'])
                    ->where('user_id', $user->user_id)
                    ->first();
            }

            if (!$application) {
                $applicant = LsankApplicant::create([
                    'user_id' => $user->user_id,
                    'applicant_type' => $this->normalizeApplicantType($validated['applicant_type'] ?? null),
                    'applicant_name' => $validated['applicant_name'] ?? $user->name ?? '-',
                    'identity_no' => $validated['identity_no'] ?? null,
                    'email' => $validated['email'] ?? $user->email ?? null,
                    'address' => $validated['address'] ?? null,
                    $phoneColumn => $validated['phone_no'] ?? $validated['phone'] ?? null,
                    'status' => 'active',
                ]);

                $application = LsankApplication::create([
                    'application_ref_no' => $this->generateDraftReferenceNo($user->user_id),
                    'user_id' => $user->user_id,
                    'applicant_id' => $applicant->applicant_id,

                    'applicant_name' => $validated['applicant_name'] ?? $user->name ?? '-',
                    'business_name' => $validated['company_name'] ?? null,
                    'phone' => $validated['phone_no'] ?? $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? $user->email ?? null,

                    'license_type' => self::TYPE_NAME,
                    'application_type' => 'effluent',
                    'application_category' => 'new',
                    'activity_type' => $this->effluentServiceName($validated['service_type_id'] ?? null),
                    'activity_name' => $this->effluentServiceName($validated['service_type_id'] ?? null),
                    'activity_details' => $this->effluentServiceName($validated['service_type_id'] ?? null),

                    'payment_status' => 'belum_bayar',
                    'application_status' => 'draf',
                    'current_step' => $validated['current_step'] ?? 0,
                    'draft_data' => $this->normalizeEffluentDraftData($validated, $application ?? null),

                    'applicant_type' => $validated['applicant_type'] ?? null,
                    'identity_no' => $validated['identity_no'] ?? null,
                    'phone_no' => $validated['phone_no'] ?? $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,

                    'company_name' => $validated['company_name'] ?? null,
                    'registration_no' => $validated['registration_no'] ?? null,
                    'business_address' => $validated['business_address'] ?? null,
                    'business_phone' => $validated['business_phone'] ?? null,
                    'business_email' => $validated['business_email'] ?? null,
                    'responsible_officer_name' => $validated['responsible_officer_name'] ?? null,
                    'responsible_officer_phone' => $validated['responsible_officer_phone'] ?? null,
                    'responsible_officer_position' => $validated['responsible_officer_position'] ?? null,
                    'officers' => $validated['officers'] ?? [],

                    'district' => $validated['district'] ?? null,
                    'activity_location' => $validated['activity_location'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'latitude' => $validated['latitude'] ?? null,

                    'application_type_id' => $typeId,
                    'application_status_id' => $statusId,
                    'submitted_at' => null,
                    'remarks' => null,
                ]);
            } else {
                $application->update([
                'applicant_name' => $validated['applicant_name'] ?? $application->applicant_name,
                'business_name' => $validated['company_name'] ?? $application->business_name,
                'phone' => $validated['phone_no'] ?? $validated['phone'] ?? $application->phone,
                'email' => $validated['email'] ?? $application->email,

                'current_step' => $validated['current_step'] ?? $application->current_step,
                'draft_data' => $this->normalizeEffluentDraftData($validated, $application),

                'applicant_type' => $validated['applicant_type'] ?? $application->applicant_type,
                'identity_no' => $validated['identity_no'] ?? $application->identity_no,
                'phone_no' => $validated['phone_no'] ?? $validated['phone'] ?? $application->phone_no,
                'address' => $validated['address'] ?? $application->address,

                'company_name' => $validated['company_name'] ?? $application->company_name,
                'registration_no' => $validated['registration_no'] ?? $application->registration_no,
                'business_address' => $validated['business_address'] ?? $application->business_address,
                'business_phone' => $validated['business_phone'] ?? $application->business_phone,
                'business_email' => $validated['business_email'] ?? $application->business_email,

                'responsible_officer_name' => $validated['responsible_officer_name'] ?? $application->responsible_officer_name,
                'responsible_officer_phone' => $validated['responsible_officer_phone'] ?? $application->responsible_officer_phone,
                'responsible_officer_position' => $validated['responsible_officer_position'] ?? $application->responsible_officer_position,

                'district' => $validated['district'] ?? $application->district,
                'activity_location' => $validated['activity_location'] ?? $application->activity_location,
                'longitude' => $validated['longitude'] ?? $application->longitude,
                'latitude' => $validated['latitude'] ?? $application->latitude,
                'activity_type' => $this->effluentServiceName($validated['service_type_id'] ?? null) ?? $application->activity_type,
                'activity_name' => $this->effluentServiceName($validated['service_type_id'] ?? null) ?? $application->activity_name,
                'activity_details' => $this->effluentServiceName($validated['service_type_id'] ?? null) ?? $application->activity_details,
                'officers' => $validated['officers'] ?? $application->officers,
            ]);
                $applicant = $application->applicant;

                if ($applicant) {
                    $applicant->update([
                        'applicant_type' => $this->normalizeApplicantType($validated['applicant_type'] ?? $applicant->applicant_type),
                        'applicant_name' => $validated['applicant_name'] ?? $applicant->applicant_name,
                        'identity_no' => $validated['identity_no'] ?? $applicant->identity_no,
                        'email' => $validated['email'] ?? $applicant->email,
                        'address' => $validated['address'] ?? $applicant->address,
                        $phoneColumn => $validated['phone_no'] ?? $validated['phone'] ?? $applicant->{$phoneColumn},
                    ]);
                }
            }

            if (!empty($validated['company_name'])) {
                $company = LsankCompany::updateOrCreate(
                    ['applicant_id' => $application->applicant_id],
                    [
                        'company_name' => $validated['company_name'],
                        'registration_no' => $validated['registration_no'] ?? null,
                        'business_address' => $validated['business_address'] ?? null,
                        'business_phone' => $validated['business_phone'] ?? null,
                        'business_email' => $validated['business_email'] ?? null,
                        'responsible_officer_name' => $validated['responsible_officer_name'] ?? null,
                        'responsible_officer_phone' => $validated['responsible_officer_phone'] ?? null,
                    ]
                );

                if (!empty($validated['responsible_officer_name'])) {
                    LsankCompanyOfficer::updateOrCreate(
                        [
                            'company_id' => $company->company_id,
                            'officer_name' => $validated['responsible_officer_name'],
                        ],
                        [
                            'officer_phone' => $validated['responsible_officer_phone'] ?? '',
                            'officer_position' => $validated['responsible_officer_position'] ?? null,
                        ]
                    );
                }
            }

            LsankEffluentApplication::updateOrCreate(
                ['application_id' => $application->application_id],
                [
                    'service_type_id' => $validated['service_type_id'] ?? null,
                    'activity_location' => $validated['activity_location'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'latitude' => $validated['latitude'] ?? null,
                    'composition' => $validated['composition'] ?? null,
                    'frequency' => $validated['frequency'] ?? null,
                    'flow_rate' => $validated['flow_rate'] ?? null,
                    'sampling_method' => $validated['sampling_method'] ?? null,
                    'contingency_plan' => $validated['contingency_plan'] ?? null,
                    'disposal_method' => $validated['disposal_method'] ?? null,
                  
                ]
            );

            $application->load([
                'applicant.company.officers',
                'status',
                'type',
                'effluent.serviceType',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Draf permohonan efluen berjaya disimpan.',
                'data' => $this->formatApplicationDetail($application, self::TYPE_NAME),
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
                'message' => 'Permohonan efluen tidak dijumpai.',
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

        $statusId = $this->applicationStatusId('payment', 'Fi Pemprosesan', 2);

        $application->application_status_id = $statusId;
        $application->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
        $application->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
        $application->submitted_at = null;
        $application->save();

        $fees = $this->calculateEffluentFees($application);

        $invoice = $this->createOrUpdateProcessingInvoice($application, $fees);

        return response()->json([
            'success' => true,
            'message' => 'Invois fi pemprosesan efluen berjaya dijana.',
            'data' => [
                'id' => $application->application_id,
                'application_id' => $application->application_id,
                'application_ids' => [$application->application_id],

                'application_no' => $application->application_ref_no,
                'application_ref_no' => $application->application_ref_no,

                'invoice_id' => $invoice->invoice_id,
                'invoice_ids' => [$invoice->invoice_id],
                'invoice_no' => $invoice->invoice_no,

                'processing_fee' => $fees['processing_fee'],
                'processing_fee_display' => 'RM ' . number_format($fees['processing_fee'], 2),

                'security_fee' => $fees['security_fee'],
                'security_fee_display' => 'RM ' . number_format($fees['security_fee'], 2),

                'license_fee' => $fees['license_fee'],
                'license_fee_display' => 'RM ' . number_format($fees['license_fee'], 2),

                'charge_fee' => $fees['charge_fee'],
                'charge_fee_display' => 'RM ' . number_format($fees['charge_fee'], 2),

                'charge_items' => $fees['charge_items'],
                'total_after_approval' => $fees['total_after_approval'],
                'total_after_approval_display' => 'RM ' . number_format($fees['total_after_approval'], 2),

                'status' => $application->application_status,
                'payment_status' => $application->payment_status,
            ],
        ]);
    }

    public function show(
    Request $request,
    LsankApplication $application
) {
    if (
        (int) $application->user_id !==
        (int) $request->user()->user_id
    ) {
        abort(
            403,
            'Anda tidak dibenarkan melihat permohonan ini.'
        );
    }

    $typeId = $this->applicationTypeId(
        self::TYPE_CODE,
        self::TYPE_NAME
    );

    if (
        (int) $application->application_type_id !==
        (int) $typeId
    ) {
        return response()->json([
            'success' => false,
            'message' =>
                'Permohonan efluen tidak dijumpai.',
        ], 404);
    }

    $application->load([
        'user',
        'applicant.company.officers',
        'status',
        'type',
        'effluent.serviceType',
        'documents',
        'reviews',
    ]);

    $detail = $this->formatApplicationDetail(
        $application,
        self::TYPE_NAME
    );

    $serviceName =
        $application->effluent?->serviceType?->service_name
        ?? '-';

    $invoiceItems = LsankInvoice::where(
            'application_id',
            $application->application_id
        )
        ->orderBy('invoice_id')
        ->get()
        ->map(function ($invoice) use (
            $application,
            $serviceName
        ) {
            return [
                'invoice_id' => $invoice->invoice_id,
                'application_id' =>
                    $application->application_id,

                'invoice_no' => $invoice->invoice_no,
                'payment_type' => 'Fi Pemprosesan',

                'amount' => (float) $invoice->total_amount,
                'amount_display' => 'RM ' . number_format(
                    (float) $invoice->total_amount,
                    2
                ),

                'invoice_date' => optional(
                    $invoice->invoice_date
                )->format('d/m/Y') ?? '-',

                'due_date' => optional(
                    $invoice->due_date
                )->format('d/m/Y') ?? '-',

                'status' => $invoice->status,
                'paid' => $invoice->status === 'paid',

                'application_ref_no' =>
                    $application->application_ref_no,
                'application_no' =>
                    $application->application_ref_no,

                'service_name' => $serviceName,
                'activity_name' => $serviceName,
                'activity_details' => $serviceName,
            ];
        })
        ->values()
        ->all();

    $receiptItems = [];

    if (
        $application->payment_status ===
            LsankApplication::PAYMENT_SUDAH_BAYAR
    ) {
        $year = optional($application->updated_at)
            ->format('Y') ?? now()->format('Y');

        $runningNo = str_pad(
            (string) $application->application_id,
            4,
            '0',
            STR_PAD_LEFT
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

        $detail['service_type_id'] = optional($application->effluent)->service_type_id;
        $detail['service_name'] = optional(optional($application->effluent)->serviceType)->service_name;
        $detail['service_code'] = optional(optional($application->effluent)->serviceType)->service_code;

        $detail['composition'] = optional($application->effluent)->composition;
        $detail['frequency'] = optional($application->effluent)->frequency;
        $detail['flow_rate'] = optional($application->effluent)->flow_rate;
        $detail['sampling_method'] = optional($application->effluent)->sampling_method;
        $detail['contingency_plan'] = optional($application->effluent)->contingency_plan;
        $detail['disposal_method'] = optional($application->effluent)->disposal_method;

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
        $paidInvoice = collect($invoiceItems)
            ->firstWhere('paid', true);

        $receiptItems[] = [
            'receipt_id' =>
                $application->application_id,

            'application_id' =>
                $application->application_id,

            'receipt_no' =>
                'RESIT-' . $year . '-' .
                $runningNo . '-01',

            'invoice_id' =>
                $paidInvoice['invoice_id'] ?? null,

            'invoice_no' =>
                $paidInvoice['invoice_no'] ?? null,

            'application_ref_no' =>
                $application->application_ref_no,

            'application_no' =>
                $application->application_ref_no,

            'payment_type' => 'Fi Pemprosesan',

            'amount' => (float) (
                $paidInvoice['amount'] ?? 150
            ),

            'amount_display' =>
                $paidInvoice['amount_display']
                ?? 'RM 150.00',

            'paid_date' => optional(
                $application->updated_at
            )->format('d/m/Y') ?? '-',

            'paid_at' => optional(
                $application->updated_at
            )->toDateTimeString(),

            'service_name' => $serviceName,
            'activity_name' => $serviceName,
            'activity_details' => $serviceName,
        ];
    }

    $detail['application_type_source'] = 'effluent';

    $detail['applicant_type'] =
        $application->applicant_type
        ?? $application->applicant?->applicant_type;

    $detail['applicant_name'] =
        $application->applicant_name
        ?? $application->applicant?->applicant_name;

    $detail['email'] =
        $application->email
        ?? $application->applicant?->email;

    $detail['phone'] =
        $application->phone
        ?? $application->phone_no;

    $detail['phone_no'] =
        $application->phone_no
        ?? $application->phone;

    $detail['company_name'] =
        $application->company_name
        ?? $application->applicant?->company?->company_name;

    $detail['business_name'] =
        $application->business_name
        ?? $application->company_name
        ?? $application->applicant?->company?->company_name;

    $detail['service_name'] = $serviceName;
    $detail['activity_name'] = $serviceName;
    $detail['activity_details'] = $serviceName;

    $detail['payment_status'] =
        $application->payment_status;

    $detail['invoice_items'] = $invoiceItems;
    $detail['receipt_items'] = $receiptItems;

    return response()->json([
        'success' => true,
        'data' => $detail,
    ]);
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
            'message' => 'Permohonan efluen tidak dijumpai.',
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

        $processingInvoice = LsankInvoice::where('application_id', $application->application_id)
            ->where('invoice_no', $this->generateProcessingInvoiceNo($application))
            ->latest('invoice_id')
            ->first();

        if (!$processingInvoice) {
            $fees = $this->calculateEffluentFees($application);
            $processingInvoice = $this->createOrUpdateProcessingInvoice($application, $fees);
        }

        $serviceCode = optional(optional($application->effluent)->serviceType)->service_code ?? '600-21';

        $application->application_ref_no = $this->generateApplicationFileNo(
            $serviceCode,
            $this->districtCode($application->district ?? null)
        );

        $application->application_status_id = $statusId;
        $application->application_status =
            LsankApplication::STATUS_DALAM_PROSES;

        $application->payment_status =
            LsankApplication::PAYMENT_SUDAH_BAYAR;

        $application->submitted_at = now();
        $application->save();
        $application->submitted_data = $application->draft_data;

        $application->remarks = trim(
            (($application->remarks ?? '') . "\nBayaran simulasi berjaya pada " . now()->format('d/m/Y H:i'))
        );

        $application->save();

        $processingInvoice->status = 'paid';
        $processingInvoice->save();

        $year = now()->format('Y');
        $runningNo = str_pad($application->application_id, 4, '0', STR_PAD_LEFT);
        $invoice = LsankInvoice::where(
        'application_id',
        $application->application_id
        )
        ->latest('invoice_id')
        ->first();

        if ($invoice) {
            $invoice->status = 'paid';
            $invoice->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Bayaran berjaya. Permohonan efluen telah dihantar untuk semakan.',
            'data' => [
                'id' => $application->application_id,
                'application_id' => $application->application_id,
                'application_ids' => [$application->application_id],

                'application_no' => $application->application_ref_no,
                'application_ref_no' => $application->application_ref_no,

                'invoice_id' => $processingInvoice->invoice_id,
                'invoice_ids' => [$processingInvoice->invoice_id],
                'invoice_no' => $processingInvoice->invoice_no,

                'receipt_id' => $application->application_id,
                'receipt_ids' => [$application->application_id],
                'receipt_no' => 'RESIT-' . $year . '-' . $runningNo . '-01',

                'status' => LsankApplication::STATUS_DALAM_PROSES,
                'status_display' => 'Dalam Proses',
                'payment_status' => LsankApplication::PAYMENT_SUDAH_BAYAR,
                'payment_status_display' => 'Sudah Bayar',
                'paid_at' => now()->toDateTimeString(),
            ],
        ]);
    });
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
            'message' => 'Permohonan efluen tidak dijumpai.',
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
        $application->effluent()->delete();
        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permohonan efluen berjaya dipadam.',
        ]);
    });
}

    private function normalizeEffluentDraftData(array $validated, ?LsankApplication $application = null): array
    {
        $incoming = $validated['draft_data'] ?? [];

        if (!is_array($incoming)) {
            $incoming = [];
        }

        $existing = ($application && is_array($application->draft_data))
            ? $application->draft_data
            : [];

        $draftData = array_replace_recursive($existing, $incoming);

        $controllers = $draftData['controllers'] ?? [];
        if (!is_array($controllers)) {
            $controllers = [];
        }

        $serviceName = $this->effluentServiceName($validated['service_type_id'] ?? null)
            ?? ($draftData['selected_service_name'] ?? ($draftData['meta']['selected_service_name'] ?? null));

        $draftData['meta'] = array_replace_recursive($draftData['meta'] ?? [], [
            'module' => 'effluent',
            'step' => $validated['current_step'] ?? ($draftData['step'] ?? ($draftData['meta']['step'] ?? 0)),
            'current_step' => $validated['current_step'] ?? ($draftData['current_step'] ?? ($draftData['meta']['current_step'] ?? 0)),
            'applicant_type' => $validated['applicant_type'] ?? ($draftData['applicant_type'] ?? null),
            'selected_service_type_id' => $validated['service_type_id'] ?? ($draftData['selected_service_type_id'] ?? null),
            'selected_service_name' => $serviceName,
            'selected_fasa' => $draftData['selected_fasa'] ?? ($draftData['meta']['selected_fasa'] ?? null),
            'sampling_at_discharge' => $validated['sampling_method'] ?? ($draftData['sampling_at_discharge'] ?? null),
        ]);

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
            'officer' => [
                'name' => $controllers['Nama Pegawai Bertanggungjawab'] ?? $validated['responsible_officer_name'] ?? null,
                'ic_no' => $controllers['No Kad Pengenalan Pegawai'] ?? null,
                'phone' => $controllers['No Telefon Pegawai Yang Boleh Dihubungi'] ?? $validated['responsible_officer_phone'] ?? null,
            ],
            'activity' => [
                'service_type_id' => $validated['service_type_id'] ?? null,
                'service_name' => $serviceName,
                'composition' => $validated['composition'] ?? null,
                'frequency' => $validated['frequency'] ?? null,
                'flow_rate' => $validated['flow_rate'] ?? null,
                'sampling_method' => $validated['sampling_method'] ?? null,
                'disposal_method' => $validated['disposal_method'] ?? null,
            ],
            'location' => [
                'search' => $validated['activity_location'] ?? ($controllers['Carian Lokasi'] ?? null),
                'longitude' => $validated['longitude'] ?? ($controllers['Longitud'] ?? null),
                'latitude' => $validated['latitude'] ?? ($controllers['Latitud'] ?? null),
            ],
        ]);

        $draftData['borang_d'] = array_replace_recursive($draftData['borang_d'] ?? [], [
            'consultant_company' => [
                'name' => $controllers['borang_d_nama_syarikat'] ?? null,
                'registration_no' => $controllers['borang_d_no_pendaftaran_syarikat'] ?? null,
                'address' => $controllers['borang_d_alamat_syarikat'] ?? null,
                'city' => $controllers['borang_d_bandar_syarikat'] ?? null,
                'postcode' => $controllers['borang_d_poskod_syarikat'] ?? null,
            ],
            'sampling' => [
                'date' => $controllers['borang_d_tarikh'] ?? null,
                'time' => $controllers['borang_d_masa'] ?? null,
                'location' => $controllers['borang_d_lokasi_persampelan_Carian Lokasi'] ?? null,
                'longitude' => $controllers['borang_d_lokasi_persampelan_Longitud'] ?? null,
                'latitude' => $controllers['borang_d_lokasi_persampelan_Latitud'] ?? null,
            ],
            'chemist' => [
                'name' => $controllers['borang_d_nama_ahli_kimia'] ?? null,
                'registration_no' => $controllers['borang_d_no_pendaftaran_ahli_kimia'] ?? null,
                'address' => $controllers['borang_d_alamat_ahli_kimia'] ?? null,
                'phone' => $controllers['borang_d_no_telefon_ahli_kimia'] ?? null,
                'email' => $controllers['borang_d_email_ahli_kimia'] ?? null,
            ],
        ]);

        $draftData['documents'] = array_replace_recursive($draftData['documents'] ?? [], [
            'uploaded_keys' => $draftData['uploaded_documents'] ?? ($draftData['documents']['uploaded_keys'] ?? []),
        ]);
        $draftData['controllers'] = $controllers;

        return $draftData;
    }

    private function effluentServiceName(?int $serviceTypeId): ?string
    {
        if (!$serviceTypeId) {
            return null;
        }

        return LsankServiceType::where('service_type_id', $serviceTypeId)
            ->value('service_name');
    }

    private function calculateEffluentFees(LsankApplication $application): array
    {
        $serviceName = strtolower(
            optional(optional($application->effluent)->serviceType)->service_name
            ?? $application->activity_name
            ?? ''
        );

        $securityFee = 0;

        if (str_contains($serviceName, 'akuakultur air tawar')) {
            $securityFee = 5000;
        } elseif (str_contains($serviceName, 'akuakultur air laut')) {
            $securityFee = 5000;
        } elseif (str_contains($serviceName, 'pembangunan') ||
            str_contains($serviceName, 'kerja tanah')) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'penternakan selain babi')) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'penternakan babi')) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'haiwan kesayangan')) {
            $securityFee = 3000;
        } elseif (str_contains($serviceName, 'kuari') ||
            str_contains($serviceName, 'perlombongan')) {
            $securityFee = 20000;
        } elseif (str_contains($serviceName, 'bengkel kenderaan') ||
            str_contains($serviceName, 'premis kedai')) {
            $securityFee = 3000;
        } elseif (str_contains($serviceName, 'pertanian')) {
            $securityFee = 5000;
        }

        return [
            'processing_fee' => 150,
            'security_fee' => $securityFee,
            'license_fee' => 0,
            'charge_fee' => 0,
            'charge_items' => [
                [
                    'title' => 'Fi Pemprosesan',
                    'description' => 'Fi pemprosesan permohonan lesen pelepasan efluen.',
                    'amount' => 150,
                    'amount_display' => 'RM 150.00',
                ],
                [
                    'title' => 'Wang Sekuriti',
                    'description' => 'Sekuriti berdasarkan jenis aktiviti pelepasan efluen.',
                    'amount' => $securityFee,
                    'amount_display' => 'RM ' . number_format($securityFee, 2),
                ],
            ],
            'total_after_approval' => $securityFee,
        ];
    }

    private function generateProcessingInvoiceNo(LsankApplication $application): string
    {
        $year = now()->format('Y');
        $runningNo = str_pad($application->application_id, 4, '0', STR_PAD_LEFT);

        return 'INVOIS-' . $year . '-' . $runningNo . '-01';
    }

    private function createOrUpdateProcessingInvoice(
        LsankApplication $application,
        array $fees
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
                'total_amount' => $fees['processing_fee'],
                'status' => 'unpaid',
            ]
        );
    }

    private function generateDraftReferenceNo(int $userId): string
{
    do {
        $refNo = 'DRAF-EFF-' . $userId . '-' . now()->format('YmdHis');
    } while (
        LsankApplication::where('application_ref_no', $refNo)->exists()
    );

    return $refNo;
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
}