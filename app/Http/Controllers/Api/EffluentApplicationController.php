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
use App\Models\LsankRenewalApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LsankInvoice;
use App\Models\LsankPayment;
use App\Models\LsankReceipt;
use App\Services\LicenseService;
use App\Services\Notifications\NotificationManager;
use App\Models\LsankLicense;

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

        $applications = LsankApplication::query()
            ->with([
                'applicant',
                'applicant.company',
                'status',
                'type',
                'effluent.serviceType',
                'license.status',
                'license.terminationRequest',
            ])
            ->where(
                'user_id',
                $request->user()->user_id
            )
            ->where(
                'application_type_id',
                $typeId
            )
            ->latest('application_id')
            ->get()
            ->map(
                fn(LsankApplication $application) =>
                $this->formatEffluentListItem($application)
            )
            ->values();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    private function formatEffluentListItem(
        LsankApplication $application
    ): array {
        $draftData = is_array($application->draft_data)
            ? $application->draft_data
            : [];

        $controllers = $draftData['controllers'] ?? [];

        if (!is_array($controllers)) {
            $controllers = [];
        }

        $borangA = $draftData['borang_a'] ?? [];

        if (!is_array($borangA)) {
            $borangA = [];
        }

        $pemohon = $borangA['pemohon'] ?? [];

        if (!is_array($pemohon)) {
            $pemohon = [];
        }

        $perniagaan = $borangA['perniagaan'] ?? [];

        if (!is_array($perniagaan)) {
            $perniagaan = [];
        }

        $effluent = $application->effluent;

        // ============================================================
        // STATUS
        // ============================================================

        $isDraft = in_array(
            strtolower(
                trim(
                    (string) $application->application_status
                )
            ),
            [
                'draf',
                'draft',
            ],
            true
        );

        // ============================================================
        // APPLICANT NAME
        // ============================================================

        $draftApplicantName = trim(
            (string) (
                $pemohon['applicant_name']
                ?? $controllers['Nama Pemohon']
                ?? ''
            )
        );

        if ($isDraft) {
            $applicantName =
                $draftApplicantName !== ''
                ? $draftApplicantName
                : trim(
                    (string) (
                        $application->applicant_name
                        ?? ''
                    )
                );
        } else {
            $applicantName = trim(
                (string) (
                    $application->applicant_name
                    ?? $application->applicant?->applicant_name
                    ?? ''
                )
            );
        }

        if ($applicantName === '') {
            $applicantName = '-';
        }

        // ============================================================
        // BUSINESS / COMPANY NAME
        // ============================================================

        $draftBusinessName = trim(
            (string) (
                $perniagaan['business_name']
                ?? $controllers['Nama Perniagaan Utama']
                ?? ''
            )
        );

        /*
     * PENTING:
     *
     * Untuk DRAF, jangan sesekali fallback kepada:
     *
     * $application->applicant?->company?->company_name
     *
     * kerana company relation mungkin datang daripada rekod
     * terdahulu user/applicant.
     */
        if ($isDraft) {
            $businessName = $draftBusinessName;

            if ($businessName === '') {
                $businessName = trim(
                    (string) (
                        $application->business_name
                        ?? $application->company_name
                        ?? ''
                    )
                );
            }
        } else {
            $businessName = trim(
                (string) (
                    $application->business_name
                    ?? $application->company_name
                    ?? $application->applicant?->company?->company_name
                    ?? ''
                )
            );
        }

        if ($businessName === '') {
            $businessName = '-';
        }

        // ============================================================
        // CONTACT
        // ============================================================

        $draftPhone = trim(
            (string) (
                $pemohon['phone']
                ?? $controllers['No Telefon']
                ?? ''
            )
        );

        $draftEmail = trim(
            (string) (
                $pemohon['email']
                ?? $controllers['E-mel']
                ?? ''
            )
        );

        $phone = $isDraft
            ? (
                $draftPhone !== ''
                ? $draftPhone
                : trim(
                    (string) (
                        $application->phone
                        ?? $application->phone_no
                        ?? ''
                    )
                )
            )
            : trim(
                (string) (
                    $application->phone
                    ?? $application->phone_no
                    ?? ''
                )
            );

        $email = $isDraft
            ? (
                $draftEmail !== ''
                ? $draftEmail
                : trim(
                    (string) (
                        $application->email
                        ?? ''
                    )
                )
            )
            : trim(
                (string) (
                    $application->email
                    ?? $application->applicant?->email
                    ?? ''
                )
            );

        if ($phone === '') {
            $phone = '-';
        }

        if ($email === '') {
            $email = '-';
        }

        // ============================================================
        // SERVICE
        // ============================================================

        $serviceName =
            $effluent?->serviceType?->service_name
            ?? $draftData['selected_service_name']
            ?? $draftData['meta']['selected_service_name']
            ?? '-';

        // ============================================================
        // LOCATION
        // ============================================================

        $draftLocation = trim(
            (string) (
                $draftData['borang_c']['location']['search']
                ?? $controllers['effluent_discharge_location_1_Carian Lokasi']
                ?? ''
            )
        );

        $activityLocation = $isDraft
            ? (
                $draftLocation !== ''
                ? $draftLocation
                : trim(
                    (string) (
                        $effluent?->activity_location
                        ?? $application->activity_location
                        ?? ''
                    )
                )
            )
            : trim(
                (string) (
                    $effluent?->activity_location
                    ?? $application->activity_location
                    ?? ''
                )
            );

        if ($activityLocation === '') {
            $activityLocation = '-';
        }

        // ============================================================
        // APPLICATION IDS
        // ============================================================

        $applicationIds = [
            $application->application_id,
        ];

        // ============================================================
        // RESPONSE
        // ============================================================

        return [
            'id' =>
            $application->application_id,

            'application_id' =>
            $application->application_id,

            'application_no' =>
            $application->application_ref_no,

            'application_ref_no' =>
            $application->application_ref_no,

            'application_ref_nos' => [
                $application->application_ref_no,
            ],

            'application_nos' => [
                $application->application_ref_no,
            ],

            'application_category' =>
            $application->application_category ?? 'new',

            'applicant_name' =>
            $applicantName,

            'business_name' =>
            $businessName,

            'company_name' =>
            $businessName,

            'phone' =>
            $phone,

            'email' =>
            $email,

            'license_type' =>
            self::TYPE_NAME,

            'service_type_id' =>
            $effluent?->service_type_id,

            'service_name' =>
            $serviceName,

            'activity_type' =>
            $serviceName,

            'activity_name' =>
            $serviceName,

            'activity_details' =>
            $serviceName,

            'activity_location' =>
            $activityLocation,

            'district' =>
            $application->district
                ?? '-',

            'status_code' =>
            $application->application_status,

            'status' =>
            $this->displayApplicationStatus(
                $application->application_status
            ),

            'application_status' =>
            $application->application_status,

            'application_status_display' =>
            $this->displayApplicationStatus(
                $application->application_status
            ),

            'payment_status' =>
            $application->payment_status,

            'payment_status_display' =>
            $this->displayPaymentStatus(
                $application->payment_status
            ),

            'current_step' =>
            $application->current_step ?? 0,

            'draft_data' =>
            $application->draft_data,

            'submitted_at' =>
            optional(
                $application->submitted_at
            )->toDateTimeString(),

            'submitted_date' =>
            optional(
                $application->submitted_at
                    ?? $application->created_at
            )->format('d M Y') ?? '-',

            'sort_date' =>
            optional(
                $application->submitted_at
                    ?? $application->created_at
            )->toIso8601String(),

            'created_at' =>
            optional(
                $application->created_at
            )->toDateTimeString(),

            'updated_at' =>
            optional(
                $application->updated_at
            )->toDateTimeString(),

            'invoice_items' =>
            $this->formatEffluentInvoiceItems(
                $applicationIds
            ),

            'receipt_items' =>
            $this->formatEffluentReceiptItems(
                $applicationIds
            ),
        ];
    }

    private function formatEffluentInvoiceItems(
        array $applicationIds
    ): array {
        return LsankInvoice::query()
            ->with('application.effluent.serviceType')
            ->whereIn(
                'application_id',
                $applicationIds
            )
            ->orderBy('application_id')
            ->orderBy('invoice_id')
            ->get()
            ->map(function (LsankInvoice $invoice) {
                $application = $invoice->application;

                $serviceName =
                    $application
                    ?->effluent
                    ?->serviceType
                    ?->service_name
                    ?? '-';

                return [
                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_no' =>
                    $invoice->invoice_no,

                    /*
                 * Ikut Water:
                 * guna jenis invoice sebenar dari DB.
                 */
                    'payment_type' =>
                    $invoice->payment_type
                        ?? 'Fi Pemprosesan',

                    'amount' =>
                    (float) $invoice->total_amount,

                    'amount_display' =>
                    'RM '
                        . number_format(
                            $invoice->total_amount,
                            2
                        ),

                    'invoice_date' =>
                    optional(
                        $invoice->invoice_date
                    )->format('d/m/Y') ?? '-',

                    'due_date' =>
                    optional(
                        $invoice->due_date
                    )->format('d/m/Y') ?? '-',

                    'status' =>
                    $invoice->status,

                    'paid' =>
                    strtolower(
                        trim(
                            (string) $invoice->status
                        )
                    ) === 'paid',

                    'security_refund_status' =>
                    $invoice->security_refund_status
                        ?? 'not_requested',

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

                    'service_name' =>
                    $serviceName,

                    'activity_name' =>
                    $serviceName,

                    'activity_details' =>
                    $serviceName,
                ];
            })
            ->values()
            ->all();
    }

    private function formatEffluentReceiptItems(
        array $applicationIds
    ): array {
        return LsankReceipt::query()
            ->with([
                'invoice.application.effluent.serviceType',
                'payment',
            ])
            ->whereHas(
                'invoice',
                fn($query) =>
                $query->whereIn(
                    'application_id',
                    $applicationIds
                )
            )
            ->latest('receipt_id')
            ->get()
            ->map(function (LsankReceipt $receipt) {
                $invoice = $receipt->invoice;
                $payment = $receipt->payment;
                $application = $invoice?->application;

                $serviceName =
                    $application
                    ?->effluent
                    ?->serviceType
                    ?->service_name
                    ?? '-';

                $amount = (float) (
                    $receipt->amount
                    ?? $payment?->amount
                    ?? $invoice?->total_amount
                    ?? 0
                );

                $paidAt =
                    $payment?->payment_date
                    ?? $receipt->receipt_date
                    ?? $receipt->created_at;

                return [
                    'receipt_id' =>
                    $receipt->receipt_id,

                    'receipt_no' =>
                    $receipt->receipt_no,

                    'application_id' =>
                    $invoice?->application_id,

                    'application_ref_no' =>
                    $application?->application_ref_no,

                    'application_no' =>
                    $application?->application_ref_no,

                    'file_no' =>
                    $application?->application_ref_no,

                    'invoice_id' =>
                    $invoice?->invoice_id,

                    'invoice_no' =>
                    $invoice?->invoice_no ?? '-',

                    'payment_id' =>
                    $payment?->payment_id,

                    'payment_type' =>
                    $invoice?->payment_type
                        ?? 'Fi Pemprosesan',

                    'amount' =>
                    $amount,

                    'paid_amount' =>
                    $amount,

                    'amount_display' =>
                    'RM '
                        . number_format(
                            $amount,
                            2
                        ),

                    'paid_amount_display' =>
                    'RM '
                        . number_format(
                            $amount,
                            2
                        ),

                    'receipt_date' =>
                    optional(
                        $receipt->receipt_date
                    )->format('d/m/Y') ?? '-',

                    'paid_date' =>
                    optional(
                        $paidAt
                    )->format('d/m/Y') ?? '-',

                    'paid_at' =>
                    optional(
                        $paidAt
                    )->toDateTimeString(),

                    'status' =>
                    $receipt->status ?? 'valid',

                    'paid' =>
                    true,

                    'service_name' =>
                    $serviceName,

                    'activity_name' =>
                    $serviceName,

                    'activity_details' =>
                    $serviceName,
                ];
            })
            ->values()
            ->all();
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
            'application_id' => ['nullable', 'integer', 'min:1'],

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

        $incomingDraftData = is_array(
            $validated['draft_data'] ?? null
        )
            ? $validated['draft_data']
            : [];

        $draftApplicantName =
            data_get(
                $incomingDraftData,
                'borang_a.pemohon.applicant_name'
            )
            ?? data_get(
                $incomingDraftData,
                'controllers.Nama Pemohon'
            );

        $effectiveApplicantName =
            $validated['applicant_name']
            ?? $draftApplicantName;

        return DB::transaction(function () use (
            $validated,
            $user,
            $typeId,
            $statusId,
            $phoneColumn,
            $effectiveApplicantName
        ) {
            $application = null;
            $requestedApplicationId = $validated['application_id'] ?? null;

            if ($requestedApplicationId) {
                $application = LsankApplication::query()
                    ->where(
                        'application_id',
                        $requestedApplicationId
                    )
                    ->where(
                        'user_id',
                        $user->user_id
                    )
                    ->where(
                        'application_type_id',
                        $typeId
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$application) {
                    return response()->json([
                        'success' => false,
                        'code' => 'DRAFT_NOT_FOUND',
                        'message' =>
                        'Draf permohonan tidak lagi wujud.',
                    ], 404);
                }

                /*
     * Sama seperti Water:
     * hanya Draf / Fi Pemprosesan boleh dikemaskini.
     *
     * Selepas bayaran berjaya dan status menjadi
     * Dalam Proses, borang tidak boleh save semula.
     */
                if (
                    !in_array(
                        $application->application_status,
                        [
                            LsankApplication::STATUS_DRAF,
                            LsankApplication::STATUS_FI_PEMPROSESAN,
                        ],
                        true
                    )
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                        'Permohonan ini tidak boleh dikemaskini '
                            . 'kerana telah dihantar untuk semakan.',
                    ], 422);
                }
            }

            if (!$application) {
                $applicant = LsankApplicant::create([
                    'user_id' => $user->user_id,
                    'applicant_type' => $this->normalizeApplicantType($validated['applicant_type'] ?? null),
                    'applicant_name' =>
                    $effectiveApplicantName
                        ?? '-',
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

                    'applicant_name' =>
                    $effectiveApplicantName
                        ?? '-',
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
                    'applicant_name' =>
                    $effectiveApplicantName
                        ?? $application->applicant_name,
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
                        'applicant_name' =>
                        $effectiveApplicantName
                            ?? $applicant->applicant_name,
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

            $existingEffluent =
                LsankEffluentApplication::query()
                ->where(
                    'application_id',
                    $application->application_id
                )
                ->first();

            LsankEffluentApplication::updateOrCreate(
                [
                    'application_id' =>
                    $application->application_id,
                ],
                [
                    'service_type_id' =>
                    array_key_exists(
                        'service_type_id',
                        $validated
                    )
                        ? $validated['service_type_id']
                        : $existingEffluent?->service_type_id,

                    'activity_location' =>
                    array_key_exists(
                        'activity_location',
                        $validated
                    )
                        ? $validated['activity_location']
                        : $existingEffluent?->activity_location,

                    'longitude' =>
                    array_key_exists(
                        'longitude',
                        $validated
                    )
                        ? $validated['longitude']
                        : $existingEffluent?->longitude,

                    'latitude' =>
                    array_key_exists(
                        'latitude',
                        $validated
                    )
                        ? $validated['latitude']
                        : $existingEffluent?->latitude,

                    'composition' =>
                    array_key_exists(
                        'composition',
                        $validated
                    )
                        ? $validated['composition']
                        : $existingEffluent?->composition,

                    'frequency' =>
                    array_key_exists(
                        'frequency',
                        $validated
                    )
                        ? $validated['frequency']
                        : $existingEffluent?->frequency,

                    'flow_rate' =>
                    array_key_exists(
                        'flow_rate',
                        $validated
                    )
                        ? $validated['flow_rate']
                        : $existingEffluent?->flow_rate,

                    'sampling_method' =>
                    array_key_exists(
                        'sampling_method',
                        $validated
                    )
                        ? $validated['sampling_method']
                        : $existingEffluent?->sampling_method,

                    'contingency_plan' =>
                    array_key_exists(
                        'contingency_plan',
                        $validated
                    )
                        ? $validated['contingency_plan']
                        : $existingEffluent?->contingency_plan,

                    'disposal_method' =>
                    array_key_exists(
                        'disposal_method',
                        $validated
                    )
                        ? $validated['disposal_method']
                        : $existingEffluent?->disposal_method,
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

    public function generateInvoice(
        Request $request,
        LsankApplication $application
    ) {
        if (
            (int) $application->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
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
                'message' => 'Permohonan efluen tidak dijumpai.',
            ], 404);
        }

        if (
            in_array(
                $application->application_status,
                [
                    LsankApplication::STATUS_DALAM_PROSES,
                    LsankApplication::STATUS_LULUS,
                    LsankApplication::STATUS_GAGAL,
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan ini telah dihantar atau telah selesai diproses.',
            ], 422);
        }

        return DB::transaction(function () use ($application) {
            $statusId = $this->applicationStatusId(
                'payment',
                'Fi Pemprosesan',
                2
            );

            $fees = $this->calculateEffluentFees(
                $application
            );

            $invoice = $this->createOrUpdateProcessingInvoice(
                $application,
                $fees
            );

            /*
            * Pastikan rekod invois menyimpan jenis pembayaran.
            * Ini membolehkan senarai dan butiran invois membaca
            * nilai sebenar daripada jadual lsank_invoices.
            */
            $application->application_status_id =
                $statusId;

            $application->application_status =
                LsankApplication::STATUS_FI_PEMPROSESAN;

            $application->payment_status =
                LsankApplication::PAYMENT_MENUNGGU_BAYARAN;

            $application->submitted_at = null;

            $application->save();

            return response()->json([
                'success' => true,
                'message' =>
                'Invois fi pemprosesan efluen berjaya dijana.',

                'data' => [
                    'id' =>
                    $application->application_id,

                    'application_id' =>
                    $application->application_id,

                    'application_ids' => [
                        $application->application_id,
                    ],

                    'application_no' =>
                    $application->application_ref_no,

                    'application_ref_no' =>
                    $application->application_ref_no,

                    'application_nos' => [
                        $application->application_ref_no,
                    ],

                    'application_ref_nos' => [
                        $application->application_ref_no,
                    ],

                    'invoice_id' =>
                    $invoice->invoice_id,

                    'invoice_ids' => [
                        $invoice->invoice_id,
                    ],

                    'invoice_no' =>
                    $invoice->invoice_no,

                    'invoice_nos' => [
                        $invoice->invoice_no,
                    ],

                    'payment_type' =>
                    $invoice->payment_type
                        ?? 'Fi Pemprosesan',

                    'processing_fee' =>
                    (float) $fees['processing_fee'],

                    'processing_fee_display' =>
                    'RM ' . number_format(
                        (float) $fees['processing_fee'],
                        2
                    ),

                    'security_fee' =>
                    (float) $fees['security_fee'],

                    'security_fee_display' =>
                    'RM ' . number_format(
                        (float) $fees['security_fee'],
                        2
                    ),

                    'license_fee' =>
                    (float) $fees['license_fee'],

                    'license_fee_display' =>
                    'RM ' . number_format(
                        (float) $fees['license_fee'],
                        2
                    ),

                    'charge_fee' =>
                    (float) $fees['charge_fee'],

                    'charge_fee_display' =>
                    'RM ' . number_format(
                        (float) $fees['charge_fee'],
                        2
                    ),

                    'charge_items' =>
                    $fees['charge_items'] ?? [],

                    'total_after_approval' =>
                    (float) $fees['total_after_approval'],

                    'total_after_approval_display' =>
                    'RM ' . number_format(
                        (float) $fees['total_after_approval'],
                        2
                    ),

                    'status' =>
                    $application->application_status,

                    'status_display' =>
                    'Fi Pemprosesan',

                    'application_status' =>
                    $application->application_status,

                    'application_status_display' =>
                    'Fi Pemprosesan',

                    'payment_status' =>
                    $application->payment_status,

                    'payment_status_display' =>
                    'Menunggu Bayaran',
                ],
            ]);
        });
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

        $detail['application_status'] =
            $application->application_status;

        $detail['application_status_display'] =
            $this->displayApplicationStatus(
                $application->application_status
            );

        $detail['status'] =
            $this->displayApplicationStatus(
                $application->application_status
            );

        $detail['status_code'] =
            $application->application_status;

        $detail['application_category'] =
            $application->application_category ?? 'new';

        $serviceName =
            $application->effluent?->serviceType?->service_name
            ?? '-';

        $applicationIds = [
            $application->application_id,
        ];

        $invoiceItems =
            $this->formatEffluentInvoiceItems(
                $applicationIds
            );

        $receiptItems =
            $this->formatEffluentReceiptItems(
                $applicationIds
            );

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

        $isDraft = in_array(
            strtolower(
                trim(
                    (string) $application->application_status
                )
            ),
            [
                'draf',
                'draft',
            ],
            true
        );

        $draftData = is_array(
            $application->draft_data
        )
            ? $application->draft_data
            : [];

        $draftBusinessName = trim(
            (string) (
                $draftData['borang_a']['perniagaan']['business_name']
                ?? $draftData['controllers']['Nama Perniagaan Utama']
                ?? ''
            )
        );

        if ($isDraft) {
            $businessName =
                $draftBusinessName !== ''
                ? $draftBusinessName
                : trim(
                    (string) (
                        $application->business_name
                        ?? $application->company_name
                        ?? ''
                    )
                );
        } else {
            $businessName = trim(
                (string) (
                    $application->business_name
                    ?? $application->company_name
                    ?? $application->applicant?->company?->company_name
                    ?? ''
                )
            );
        }

        $detail['company_name'] =
            $businessName !== ''
            ? $businessName
            : null;

        $detail['business_name'] =
            $businessName !== ''
            ? $businessName
            : null;

        $detail['service_name'] = $serviceName;
        $detail['activity_name'] = $serviceName;
        $detail['activity_details'] = $serviceName;

        $detail['payment_status'] =
            $application->payment_status;

        $detail['payment_status_display'] =
            $this->displayPaymentStatus(
                $application->payment_status
            );

        $detail['current_step'] =
            $application->current_step ?? 0;

        $detail['draft_data'] =
            $application->draft_data ?? [];

        $detail['review_data'] =
            $application->review_data ?? [];

        $detail['submitted_data'] =
            $application->submitted_data ?? [];

        $detail['submitted_at'] =
            optional(
                $application->submitted_at
            )->toDateTimeString();

        $detail['invoice_items'] = $invoiceItems;
        $detail['receipt_items'] = $receiptItems;

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function pay(
        Request $request,
        LsankApplication $application
    ) {
        if (
            (int) $application->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan tidak dijumpai.',
            ], 404);
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

        if (
            $application->application_status !==
            LsankApplication::STATUS_FI_PEMPROSESAN
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan ini belum berada di '
                    . 'peringkat Fi Pemprosesan.',
            ], 422);
        }

        $invoice = LsankInvoice::query()
            ->where(
                'application_id',
                $application->application_id
            )
            ->where(
                'payment_type',
                'Fi Pemprosesan'
            )
            ->where(
                'status',
                'unpaid'
            )
            ->orderBy('invoice_id')
            ->first();

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' =>
                'Tiada invois Fi Pemprosesan '
                    . 'yang belum dibayar.',
            ], 422);
        }

        return $this->payInvoice(
            $request,
            $application,
            $invoice
        );
    }

    public function payInvoice(
        Request $request,
        LsankApplication $application,
        LsankInvoice $invoice
    ) {
        /*
     * Pastikan permohonan milik pengguna.
     */
        if (
            (int) $application->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        /*
     * Pastikan permohonan ialah permohonan efluen.
     */
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
                'message' => 'Permohonan efluen tidak dijumpai.',
            ], 404);
        }

        /*
     * Pastikan invois tersebut milik permohonan ini.
     */
        if (
            (int) $invoice->application_id !==
            (int) $application->application_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Invois tidak sepadan dengan permohonan ini.',
            ], 422);
        }

        /*
     * Pastikan invois milik pengguna.
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
     * Pastikan permohonan berada di peringkat pembayaran.
     */
        if (
            $application->application_status !==
            LsankApplication::STATUS_FI_PEMPROSESAN
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan ini belum berada di peringkat fi pemprosesan.',
            ], 422);
        }

        /*
        * Endpoint ini hanya untuk Fi Pemprosesan.
        * Fi Lesen, Fi Caj dan Wang Sekuriti
        * mesti melalui payFinalInvoice().
        */
        if (
            trim(
                (string) (
                    $invoice->payment_type ?? ''
                )
            ) !== 'Fi Pemprosesan'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Invois ini bukan invois Fi Pemprosesan.',
            ], 422);
        }

        /*
     * Pastikan invois belum dibayar.
     */
        if (
            strtolower(trim((string) $invoice->status)) ===
            'paid'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini telah dibayar.',
            ], 422);
        }

        /*
     * Elakkan resit berganda.
     */
        if (
            LsankReceipt::where(
                'invoice_id',
                $invoice->invoice_id
            )->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Bayaran bagi invois ini telah direkodkan.',
            ], 422);
        }

        return DB::transaction(function () use (
            $application,
            $invoice,
            $request
        ) {
            /*
         * Lock application dan invoice.
         */
            $lockedApplication = LsankApplication::query()
                ->where(
                    'application_id',
                    $application->application_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice = LsankInvoice::query()
                ->where(
                    'invoice_id',
                    $invoice->invoice_id
                )
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->where(
                    'payment_type',
                    'Fi Pemprosesan'
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                strtolower(
                    trim((string) $lockedInvoice->status)
                ) === 'paid'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invois ini telah dibayar.',
                ], 422);
            }

            /*
         * Dapatkan status Dalam Proses.
         */
            $statusId = $this->applicationStatusId(
                'in_process',
                'Dalam Proses',
                3
            );

            /*
         * Dapatkan kod jenis perkhidmatan efluen.
         */
            $lockedApplication->loadMissing(
                'effluent.serviceType'
            );

            $serviceCode = optional(
                optional(
                    $lockedApplication->effluent
                )->serviceType
            )->service_code ?? '600-21';

            /*
         * Jana nombor fail sebenar jika masih nombor draf.
         */
            if (
                empty($lockedApplication->application_ref_no) ||
                str_starts_with(
                    strtoupper(
                        $lockedApplication->application_ref_no
                    ),
                    'DRAF-'
                )
            ) {
                $lockedApplication->application_ref_no =
                    $this->generateApplicationFileNo(
                        $serviceCode,
                        $this->districtCode(
                            $lockedApplication->district
                        )
                    );
            }

            /*
         * Kemaskini status permohonan.
         */
            $lockedApplication->application_status_id =
                $statusId;

            $lockedApplication->application_status =
                LsankApplication::STATUS_DALAM_PROSES;

            $lockedApplication->payment_status =
                LsankApplication::PAYMENT_SUDAH_BAYAR;

            $lockedApplication->submitted_at = now();

            $lockedApplication->submitted_data =
                $lockedApplication->draft_data;

            $lockedApplication->remarks = trim(
                ($lockedApplication->remarks ?? '')
                    . "\nBayaran satu invois efluen berjaya pada "
                    . now()->format('d/m/Y H:i')
            );

            $lockedApplication->save();
            /*
 * Jika permohonan ini ialah pembaharuan,
 * tukar status renewal daripada draft kepada in_process.
 */
            $renewal = LsankRenewalApplication::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->where(
                    'renewal_status',
                    LsankRenewalApplication::STATUS_DRAFT
                )
                ->lockForUpdate()
                ->first();

            if ($renewal) {
                $renewal->renewal_status =
                    LsankRenewalApplication::STATUS_IN_PROCESS;

                $renewal->save();
            }

            /*
         * Tandakan hanya invois yang dipilih sebagai paid.
         */
            $lockedInvoice->status = 'paid';
            $lockedInvoice->save();

            /*
         * Simpan bayaran.
         */
            $payment = LsankPayment::updateOrCreate(
                [
                    'invoice_id' =>
                    $lockedInvoice->invoice_id,
                ],
                [
                    'payment_method_id' => null,

                    'amount' =>
                    (float) $lockedInvoice->total_amount,

                    'payment_status' =>
                    'successful',

                    'payment_date' => now(),

                    'transaction_ref_no' =>
                    'TEST-EFF-SINGLE-'
                        . $lockedApplication->application_id
                        . '-'
                        . $lockedInvoice->invoice_id
                        . '-'
                        . now()->format('YmdHis'),
                ]
            );

            /*
         * Jana nombor resit.
         */
            $year = now()->format('Y');

            $runningNo = str_pad(
                (string) $lockedInvoice->invoice_id,
                4,
                '0',
                STR_PAD_LEFT
            );

            $receiptNo =
                'RESIT-'
                . $year
                . '-'
                . $runningNo
                . '-01';

            /*
         * Simpan resit.
         */
            $receipt = LsankReceipt::updateOrCreate(
                [
                    'invoice_id' =>
                    $lockedInvoice->invoice_id,
                ],
                [
                    'receipt_no' =>
                    $receiptNo,

                    'payment_id' =>
                    $payment->payment_id,

                    'receipt_date' =>
                    now()->toDateString(),

                    'amount' =>
                    (float) $lockedInvoice->total_amount,

                    'receipt_pdf_path' => null,

                    'status' => 'valid',
                ]
            );

            /*
             * Notification event:
             * An Effluent application is officially submitted for review
             * after the processing fee is paid and its status becomes
             * "Dalam Proses".
             */
            $this->scheduleApplicationSubmittedNotification(
                $lockedApplication,
                (int) $request->user()->getKey()
            );

            return response()->json([
                'success' => true,

                'message' =>
                'Bayaran invois berjaya. Permohonan efluen telah dihantar untuk semakan.',

                'data' => [
                    'id' =>
                    $lockedApplication->application_id,

                    'application_id' =>
                    $lockedApplication->application_id,

                    'application_ids' => [
                        $lockedApplication->application_id,
                    ],

                    'application_no' =>
                    $lockedApplication->application_ref_no,

                    'application_ref_no' =>
                    $lockedApplication->application_ref_no,

                    'application_nos' => [
                        $lockedApplication->application_ref_no,
                    ],

                    'application_ref_nos' => [
                        $lockedApplication->application_ref_no,
                    ],

                    'invoice_id' =>
                    $lockedInvoice->invoice_id,

                    'invoice_ids' => [
                        $lockedInvoice->invoice_id,
                    ],

                    'invoice_no' =>
                    $lockedInvoice->invoice_no,

                    'invoice_nos' => [
                        $lockedInvoice->invoice_no,
                    ],

                    'payment_id' =>
                    $payment->payment_id,

                    'payment_ids' => [
                        $payment->payment_id,
                    ],

                    'receipt_id' =>
                    $receipt->receipt_id,

                    'receipt_ids' => [
                        $receipt->receipt_id,
                    ],

                    'receipt_no' =>
                    $receipt->receipt_no,

                    'receipt_nos' => [
                        $receipt->receipt_no,
                    ],

                    'payment_type' =>
                    $lockedInvoice->payment_type
                        ?? 'Fi Pemprosesan',

                    'processing_fee' =>
                    (float) $lockedInvoice->total_amount,

                    'processing_fee_display' =>
                    'RM ' . number_format(
                        (float) $lockedInvoice->total_amount,
                        2
                    ),

                    'amount' =>
                    (float) $lockedInvoice->total_amount,

                    'status' =>
                    LsankApplication::STATUS_DALAM_PROSES,

                    'status_display' =>
                    'Dalam Proses',

                    'application_status' =>
                    LsankApplication::STATUS_DALAM_PROSES,

                    'application_status_display' =>
                    'Dalam Proses',

                    'payment_status' =>
                    LsankApplication::PAYMENT_SUDAH_BAYAR,

                    'payment_status_display' =>
                    'Sudah Bayar',

                    'paid_at' =>
                    now()->toDateTimeString(),
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
        /*
     * Pastikan permohonan milik pengguna.
     */
        if (
            (int) $application->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        /*
     * Pastikan permohonan ialah Efluen.
     */
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

        /*
     * Pastikan invois milik permohonan ini.
     */
        if (
            (int) $invoice->application_id !==
            (int) $application->application_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Invois tidak sepadan dengan permohonan ini.',
            ], 422);
        }

        /*
     * Pastikan invois milik pengguna.
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
     * Pindaan hanya melibatkan:
     * - Fi Pindaan Maklumat
     * - Fi Caj
     *
     * Permohonan baharu/pembaharuan:
     * - Fi Lesen
     * - Fi Caj
     * - Wang Sekuriti / Fi Sekuriti
     */
        $finalPaymentTypes = $application->isAmendment()
            ? [
                'Fi Pindaan Maklumat',
                'Fi Caj',
            ]
            : [
                'Fi Lesen',
                'Fi Caj',
                'Wang Sekuriti',
                'Fi Sekuriti',
            ];

        $paymentType = trim(
            (string) $invoice->payment_type
        );

        if (
            !in_array(
                $paymentType,
                $finalPaymentTypes,
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Invois ini bukan invois bayaran akhir.',
            ], 422);
        }

        /*
     * Bayaran akhir hanya boleh dibuat selepas
     * kelulusan Ketua Pengarah.
     */
        if (!$application->isDirectorApproved()) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan belum diluluskan oleh Ketua Pengarah.',
            ], 422);
        }

        if (
            strtolower(
                trim((string) $invoice->status)
            ) === 'paid'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invois ini telah dibayar.',
            ], 422);
        }

        if (
            LsankReceipt::query()
            ->where(
                'invoice_id',
                $invoice->invoice_id
            )
            ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Bayaran bagi invois ini telah direkodkan.',
            ], 422);
        }

        return DB::transaction(function () use (
            $application,
            $invoice,
            $licenseService
        ) {
            /*
         * Lock permohonan.
         */
            $lockedApplication =
                LsankApplication::query()
                ->where(
                    'application_id',
                    $application->application_id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedApplication->isDirectorApproved()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Permohonan belum diluluskan oleh Ketua Pengarah.',
                ], 422);
            }

            $finalPaymentTypes =
                $lockedApplication->isAmendment()
                ? [
                    'Fi Pindaan Maklumat',
                    'Fi Caj',
                ]
                : [
                    'Fi Lesen',
                    'Fi Caj',
                    'Wang Sekuriti',
                    'Fi Sekuriti',
                ];

            /*
         * Lock invois yang dipilih.
         */
            $lockedInvoice =
                LsankInvoice::query()
                ->where(
                    'invoice_id',
                    $invoice->invoice_id
                )
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->whereIn(
                    'payment_type',
                    $finalPaymentTypes
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                strtolower(
                    trim((string) $lockedInvoice->status)
                ) === 'paid'
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Invois ini telah dibayar.',
                ], 422);
            }

            $existingReceipt =
                LsankReceipt::query()
                ->where(
                    'invoice_id',
                    $lockedInvoice->invoice_id
                )
                ->lockForUpdate()
                ->first();

            if ($existingReceipt) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Bayaran bagi invois ini telah direkodkan.',
                ], 422);
            }

            /*
         * Tandakan invois semasa sebagai paid.
         */
            $lockedInvoice->status = 'paid';
            $lockedInvoice->save();

            /*
         * Simpan transaksi bayaran.
         */
            $payment = LsankPayment::updateOrCreate(
                [
                    'invoice_id' =>
                    $lockedInvoice->invoice_id,
                ],
                [
                    'payment_method_id' => null,

                    'amount' =>
                    (float) $lockedInvoice->total_amount,

                    'payment_status' =>
                    'successful',

                    'payment_date' =>
                    now(),

                    'transaction_ref_no' =>
                    'TEST-EFF-FINAL-'
                        . $lockedApplication->application_id
                        . '-'
                        . $lockedInvoice->invoice_id
                        . '-'
                        . now()->format('YmdHis'),
                ]
            );

            /*
         * Kod resit mengikut jenis fi.
         */
            $feeCode = match (trim((string) $lockedInvoice->payment_type)) {
                'Fi Lesen' => '02',
                'Fi Caj' => '03',
                'Wang Sekuriti' => '04',
                'Fi Sekuriti' => '04',
                'Fi Pindaan Maklumat' => '05',
                default => '00',
            };

            $year = now()->format('Y');

            $runningNo = str_pad(
                (string) $lockedInvoice->invoice_id,
                4,
                '0',
                STR_PAD_LEFT
            );

            $receiptNo =
                'RESIT-'
                . $year
                . '-'
                . $runningNo
                . '-'
                . $feeCode;

            /*
         * Simpan resit.
         */
            $receipt = LsankReceipt::updateOrCreate(
                [
                    'invoice_id' =>
                    $lockedInvoice->invoice_id,
                ],
                [
                    'receipt_no' =>
                    $receiptNo,

                    'payment_id' =>
                    $payment->payment_id,

                    'receipt_date' =>
                    now()->toDateString(),

                    'amount' =>
                    (float) $lockedInvoice->total_amount,

                    'receipt_pdf_path' =>
                    null,

                    'status' =>
                    'valid',
                ]
            );

            /*
         * Semak baki invois akhir yang belum dibayar.
         */
            $remainingInvoices =
                LsankInvoice::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->whereIn(
                    'payment_type',
                    $finalPaymentTypes
                )
                ->where(
                    'status',
                    '!=',
                    'paid'
                )
                ->orderBy('invoice_id')
                ->get();

            /*
         * Masih ada invois belum dibayar.
         */
            if ($remainingInvoices->isNotEmpty()) {
                $reviewData = is_array(
                    $lockedApplication->review_data
                )
                    ? $lockedApplication->review_data
                    : [];

                $reviewData['final_invoice_status'] =
                    'pending_payment';

                $reviewData['last_final_invoice_paid_at'] =
                    now()->toDateTimeString();

                $lockedApplication->payment_status =
                    LsankApplication::PAYMENT_MENUNGGU_BAYARAN;

                $lockedApplication->review_data =
                    $reviewData;

                $lockedApplication->save();

                return response()->json([
                    'success' => true,

                    'message' =>
                    'Bayaran invois berjaya. '
                        . 'Masih terdapat invois yang belum dibayar.',

                    'data' => [
                        'id' =>
                        $lockedApplication->application_id,

                        'application_id' =>
                        $lockedApplication->application_id,

                        'application_ids' => [
                            $lockedApplication->application_id,
                        ],

                        'application_no' =>
                        $lockedApplication
                            ->application_ref_no,

                        'application_ref_no' =>
                        $lockedApplication
                            ->application_ref_no,

                        'application_nos' => [
                            $lockedApplication
                                ->application_ref_no,
                        ],

                        'application_ref_nos' => [
                            $lockedApplication
                                ->application_ref_no,
                        ],

                        'invoice_id' =>
                        $lockedInvoice->invoice_id,

                        'invoice_ids' => [
                            $lockedInvoice->invoice_id,
                        ],

                        'invoice_no' =>
                        $lockedInvoice->invoice_no,

                        'invoice_nos' => [
                            $lockedInvoice->invoice_no,
                        ],

                        'payment_type' =>
                        $lockedInvoice->payment_type,

                        'payment_id' =>
                        $payment->payment_id,

                        'payment_ids' => [
                            $payment->payment_id,
                        ],

                        'receipt_id' =>
                        $receipt->receipt_id,

                        'receipt_ids' => [
                            $receipt->receipt_id,
                        ],

                        'receipt_no' =>
                        $receipt->receipt_no,

                        'receipt_nos' => [
                            $receipt->receipt_no,
                        ],

                        'amount' =>
                        (float) $lockedInvoice
                            ->total_amount,

                        'total_amount' =>
                        (float) $lockedInvoice
                            ->total_amount,

                        'invoice_payment_status' =>
                        'paid',

                        'payment_status' =>
                        LsankApplication::PAYMENT_SUDAH_BAYAR,

                        'application_payment_status' =>
                        $lockedApplication
                            ->payment_status,

                        'application_status' =>
                        $lockedApplication
                            ->application_status,

                        'application_status_display' =>
                        'Lulus - Menunggu Bayaran Lengkap',

                        'paid_at' =>
                        now()->toDateTimeString(),

                        'payment_date' =>
                        now()->toDateTimeString(),

                        'remaining_unpaid_invoice_count' =>
                        $remainingInvoices->count(),

                        'remaining_invoices' =>
                        $remainingInvoices
                            ->map(
                                fn(
                                    LsankInvoice $item
                                ) => [
                                    'invoice_id' =>
                                    $item->invoice_id,

                                    'invoice_no' =>
                                    $item->invoice_no,

                                    'payment_type' =>
                                    $item->payment_type,

                                    'amount' =>
                                    (float) $item
                                        ->total_amount,

                                    'status' =>
                                    $item->status,
                                ]
                            )
                            ->values()
                            ->all(),

                        'all_final_invoices_paid' =>
                        false,

                        'license_generated' =>
                        false,

                        'license_updated' =>
                        false,
                    ],
                ]);
            }

            /*
         * Semua invois akhir sudah dibayar.
         */
            $reviewData = is_array(
                $lockedApplication->review_data
            )
                ? $lockedApplication->review_data
                : [];

            $reviewData['final_invoice_status'] =
                'paid';

            $reviewData['final_paid_at'] =
                now()->toDateTimeString();

            $lockedApplication->payment_status =
                LsankApplication::PAYMENT_SUDAH_BAYAR;

            $lockedApplication->review_data =
                $reviewData;

            $lockedApplication->save();

            $isAmendment =
                $lockedApplication->isAmendment();

            $isRenewal =
                strtolower(
                    trim(
                        (string)
                        $lockedApplication->application_category
                    )
                ) === 'renewal';

            $isLicenseUpdate =
                $isAmendment || $isRenewal;

            /*
         * Pindaan mengemas kini lesen asal.
         * Permohonan baharu/pembaharuan menggunakan
         * generateForApprovedApplication().
         */
            $license = $isAmendment
                ? $licenseService->applyApprovedAmendment(
                    $lockedApplication->fresh()
                )
                : $licenseService
                ->generateForApprovedApplication(
                    $lockedApplication->fresh()
                );

            /*
         * Pautkan semua invois akhir kepada lesen.
         */
            LsankInvoice::query()
                ->where(
                    'application_id',
                    $lockedApplication->application_id
                )
                ->whereIn(
                    'payment_type',
                    $finalPaymentTypes
                )
                ->update([
                    'license_id' =>
                    $license->license_id,
                ]);

            $freshApplication =
                $lockedApplication->fresh();

            $latestReviewData = is_array(
                $freshApplication->review_data
            )
                ? $freshApplication->review_data
                : [];

            $latestReviewData['license_generation_status'] =
                $isLicenseUpdate
                ? 'updated'
                : 'generated';

            $latestReviewData['license_id'] =
                $license->license_id;

            $latestReviewData['license_no'] =
                $license->license_no;

            $latestReviewData[$isLicenseUpdate
                ? 'license_updated_at'
                : 'license_generated_at'] = now()->toDateTimeString();

            $freshApplication->review_data =
                $latestReviewData;

            $freshApplication->save();

            return response()->json([
                'success' => true,

                'message' => $isAmendment
                    ? 'Semua invois pindaan telah dibayar. '
                    . 'Lesen asal berjaya dikemas kini.'
                    : (
                        $isRenewal
                        ? 'Semua invois pembaharuan telah dibayar. '
                        . 'Lesen asal berjaya dikemas kini.'
                        : 'Semua invois telah dibayar. '
                        . 'Lesen berjaya dijana.'
                    ),

                'data' => [
                    'id' =>
                    $freshApplication->application_id,

                    'application_id' =>
                    $freshApplication->application_id,

                    'application_ids' => [
                        $freshApplication->application_id,
                    ],

                    'application_no' =>
                    $freshApplication
                        ->application_ref_no,

                    'application_ref_no' =>
                    $freshApplication
                        ->application_ref_no,

                    'application_nos' => [
                        $freshApplication
                            ->application_ref_no,
                    ],

                    'application_ref_nos' => [
                        $freshApplication
                            ->application_ref_no,
                    ],

                    'invoice_id' =>
                    $lockedInvoice->invoice_id,

                    'invoice_ids' => [
                        $lockedInvoice->invoice_id,
                    ],

                    'invoice_no' =>
                    $lockedInvoice->invoice_no,

                    'invoice_nos' => [
                        $lockedInvoice->invoice_no,
                    ],

                    'payment_type' =>
                    $lockedInvoice->payment_type,

                    'payment_id' =>
                    $payment->payment_id,

                    'payment_ids' => [
                        $payment->payment_id,
                    ],

                    'receipt_id' =>
                    $receipt->receipt_id,

                    'receipt_ids' => [
                        $receipt->receipt_id,
                    ],

                    'receipt_no' =>
                    $receipt->receipt_no,

                    'receipt_nos' => [
                        $receipt->receipt_no,
                    ],

                    'amount' =>
                    (float) $lockedInvoice
                        ->total_amount,

                    'total_amount' =>
                    (float) $lockedInvoice
                        ->total_amount,

                    'payment_status' =>
                    LsankApplication::PAYMENT_SUDAH_BAYAR,

                    'application_payment_status' =>
                    $freshApplication
                        ->payment_status,

                    'application_status' =>
                    $freshApplication
                        ->application_status,

                    'application_status_display' =>
                    'Lesen Aktif',

                    'status_display' =>
                    'Lesen Aktif',

                    'paid_at' =>
                    now()->toDateTimeString(),

                    'payment_date' =>
                    now()->toDateTimeString(),

                    'remaining_unpaid_invoice_count' =>
                    0,

                    'all_final_invoices_paid' =>
                    true,

                    'license_generated' =>
                    true,

                    'license_updated' =>
                    $isLicenseUpdate,

                    'license_id' =>
                    $license->license_id,

                    'license_no' =>
                    $license->license_no,

                    'license_start_date' =>
                    optional(
                        $license->start_date
                    )->format('Y-m-d'),

                    'license_expiry_date' =>
                    optional(
                        $license->expiry_date
                    )->format('Y-m-d'),

                    'license_status' =>
                    $license->display_status,
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
        } elseif (
            str_contains($serviceName, 'pembangunan') ||
            str_contains($serviceName, 'kerja tanah')
        ) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'penternakan selain babi')) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'penternakan babi')) {
            $securityFee = 10000;
        } elseif (str_contains($serviceName, 'haiwan kesayangan')) {
            $securityFee = 3000;
        } elseif (
            str_contains($serviceName, 'kuari') ||
            str_contains($serviceName, 'perlombongan')
        ) {
            $securityFee = 20000;
        } elseif (
            str_contains($serviceName, 'bengkel kenderaan') ||
            str_contains($serviceName, 'premis kedai')
        ) {
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

    private function generateProcessingInvoiceNo(
        LsankApplication $application
    ): string {
        $year = now()->format('Y');

        $lastInvoice = LsankInvoice::where(
            'invoice_no',
            'like',
            'INVOIS-' . $year . '-%'
        )
            ->orderByDesc('invoice_id')
            ->first();

        $nextNumber = 1;

        if ($lastInvoice) {
            preg_match(
                '/INVOIS-\d{4}-(\d+)-\d+/',
                $lastInvoice->invoice_no,
                $matches
            );

            if (!empty($matches[1])) {
                $nextNumber = ((int) $matches[1]) + 1;
            }
        }

        $runningNo = str_pad(
            (string) $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        return 'INVOIS-' . $year . '-' . $runningNo . '-01';
    }

    private function createOrUpdateProcessingInvoice(
        LsankApplication $application,
        array $fees
    ): LsankInvoice {
        $invoice = LsankInvoice::query()
            ->where(
                'application_id',
                $application->application_id
            )
            ->where(
                'payment_type',
                'Fi Pemprosesan'
            )
            ->whereNotIn(
                'status',
                ['cancelled', 'void']
            )
            ->latest('invoice_id')
            ->first();

        if ($invoice) {
            if (
                strtolower(
                    trim((string) $invoice->status)
                ) !== 'paid'
            ) {
                $invoice->user_id =
                    $application->user_id;

                $invoice->payment_type =
                    'Fi Pemprosesan';

                $invoice->invoice_date =
                    $invoice->invoice_date
                    ?? now()->toDateString();

                $invoice->due_date =
                    $invoice->due_date
                    ?? now()->addDays(14)->toDateString();

                $invoice->total_amount =
                    (float) $fees['processing_fee'];

                $invoice->status = 'unpaid';

                $invoice->security_refund_status =
                    $invoice->security_refund_status
                    ?? 'not_requested';

                $invoice->save();
            }

            return $invoice;
        }

        $invoiceNo =
            $this->generateProcessingInvoiceNo(
                $application
            );

        if (
            LsankInvoice::where(
                'invoice_no',
                $invoiceNo
            )->exists()
        ) {
            throw new \RuntimeException(
                "Nombor invois {$invoiceNo} telah digunakan."
            );
        }

        return LsankInvoice::create([
            'application_id' =>
            $application->application_id,

            'user_id' =>
            $application->user_id,

            'invoice_no' =>
            $invoiceNo,

            'payment_type' =>
            'Fi Pemprosesan',

            'invoice_date' =>
            now()->toDateString(),

            'due_date' =>
            now()->addDays(14)->toDateString(),

            'total_amount' =>
            (float) $fees['processing_fee'],

            'status' =>
            'unpaid',

            'security_refund_status' =>
            'not_requested',
        ]);
    }

    /**
     * Queue the Effluent application.submitted notification only after the
     * payment/application database transaction has committed.
     *
     * The core application flow must remain successful even when a
     * notification provider is temporarily unavailable.
     */
    private function scheduleApplicationSubmittedNotification(
        LsankApplication $application,
        int $actorUserId
    ): void {
        $applicationId = (int) $application->application_id;

        DB::afterCommit(function () use (
            $applicationId,
            $actorUserId
        ): void {
            try {
                $freshApplication = LsankApplication::query()
                    ->with([
                        'applicant.company',
                        'effluent.serviceType',
                    ])
                    ->where(
                        'application_id',
                        $applicationId
                    )
                    ->first();

                if (!$freshApplication) {
                    Log::warning(
                        'Effluent application notification skipped: application not found.',
                        [
                            'application_id' => $applicationId,
                            'event_type' => 'application.submitted',
                        ]
                    );

                    return;
                }

                $serviceName =
                    $freshApplication
                        ->effluent
                        ?->serviceType
                        ?->service_name
                    ?? $freshApplication->activity_name
                    ?? $freshApplication->activity_details
                    ?? '';

                app(NotificationManager::class)->dispatch(
                    'application.submitted',
                    [
                        'source_type' => 'application',
                        'source_id' =>
                            (int) $freshApplication->application_id,

                        'application' => $freshApplication,

                        'application_type' => 'effluent',

                        'application_no' =>
                            $freshApplication->application_ref_no
                            ?? (
                                'APP-'
                                . $freshApplication->application_id
                            ),

                        'user_id' =>
                            (int) $freshApplication->user_id,

                        'applicant_name' =>
                            $freshApplication->applicant_name
                            ?? $freshApplication->applicant?->applicant_name
                            ?? $freshApplication->company_name
                            ?? $freshApplication->business_name
                            ?? '',

                        'actor_user_id' => $actorUserId,

                        'action_url' => '/applications/type',

                        'metadata' => [
                            'module' => 'effluent',
                            'application_status' =>
                                $freshApplication->application_status,
                            'payment_status' =>
                                $freshApplication->payment_status,
                            'application_type_id' =>
                                $freshApplication->application_type_id,
                            'applicant_id' =>
                                $freshApplication->applicant_id,
                            'service_name' => $serviceName,
                            'district' =>
                                $freshApplication->district,
                        ],
                    ]
                );
            } catch (\Throwable $exception) {
                Log::error(
                    'Failed to dispatch Effluent application.submitted notification.',
                    [
                        'application_id' => $applicationId,
                        'actor_user_id' => $actorUserId,
                        'event_type' => 'application.submitted',
                        'error' => $exception->getMessage(),
                    ]
                );

                report($exception);
            }
        });
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
