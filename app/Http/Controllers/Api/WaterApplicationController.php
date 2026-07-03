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
                return [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,
                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,

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
                $application->application_ref_no = $this->generateDraftReferenceNo();
                $application->user_id = $user->user_id;
                $application->application_type_id = $typeId;
                $application->application_category = 'new';
            }

            $application->applicant_id = $applicant->applicant_id;
            $application->application_status_id = $statusId;
            $application->application_status = LsankApplication::STATUS_DRAF;
            $application->payment_status = LsankApplication::PAYMENT_BELUM_BAYAR;
            $application->current_step = $validated['current_step'] ?? 0;
            $application->draft_data = $validated['draft_data'] ?? $request->all();
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
                'message' => 'Draf permohonan badan perairan berjaya disimpan.',
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

        $statusId = $this->applicationStatusId('payment', 'Fi Pemprosesan', 2);

        if (
            empty($application->application_ref_no) ||
            $application->application_ref_no === 'NULL' ||
            str_starts_with($application->application_ref_no, 'DRAF-')
        ) {
            $application->application_ref_no = $this->generateApplicationFileNo(
                $this->waterSectionCode($application->activity_name ?? null),
                $this->districtCode($application->district ?? null)
            );
        }

        $application->application_status_id = $statusId;
        $application->application_status = LsankApplication::STATUS_FI_PEMPROSESAN;
        $application->payment_status = LsankApplication::PAYMENT_MENUNGGU_BAYARAN;
        $application->submitted_at = null;
        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Invois fi pemprosesan berjaya dijana.',
            'data' => [
                'id' => $application->application_id,
                'application_id' => $application->application_id,
                'application_no' => $application->application_ref_no,
                'application_ref_no' => $application->application_ref_no,
                'invoice_id' => $application->application_id,
                'status' => $application->application_status,
                'payment_status' => $application->payment_status,
            ],
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
                'message' => 'Permohonan badan perairan tidak dijumpai.',
            ], 404);
        }

        if ($application->application_status !== LsankApplication::STATUS_FI_PEMPROSESAN) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini belum berada di peringkat fi pemprosesan.',
            ], 422);
        }

        $statusId = $this->applicationStatusId('in_process', 'Dalam Proses', 3);

        $application->application_status_id = $statusId;
        $application->application_status = LsankApplication::STATUS_DALAM_PROSES;
        $application->payment_status = LsankApplication::PAYMENT_SUDAH_BAYAR;
        $application->submitted_at = now();

        $application->remarks = trim(
            (($application->remarks ?? '') . "\nBayaran simulasi berjaya pada " . now()->format('d/m/Y H:i'))
        );

        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Bayaran berjaya. Permohonan telah dihantar untuk semakan.',
            'data' => [
                'id' => $application->application_id,
                'application_id' => $application->application_id,
                'application_no' => $application->application_ref_no,
                'application_ref_no' => $application->application_ref_no,

                'receipt_id' => $application->application_id,
                'receipt_no' => 'RESIT-' . now()->format('Y') . '-' . str_pad($application->application_id, 4, '0', STR_PAD_LEFT),

                'status' => $application->application_status,
                'status_display' => 'Dalam Proses',
                'payment_status' => $application->payment_status,
                'payment_status_display' => 'Sudah Bayar',
                'paid_at' => now()->toDateTimeString(),
            ],
        ]);
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

        if ($application->application_status !== LsankApplication::STATUS_DRAF) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permohonan berstatus draf sahaja boleh dipadam.',
            ], 422);
        }

        if ($application->payment_status !== LsankApplication::PAYMENT_BELUM_BAYAR) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan ini tidak boleh dipadam kerana telah memasuki proses bayaran.',
            ], 422);
        }

        return DB::transaction(function () use ($application) {
            $application->documents()->delete();
            $application->waterBody()->delete();
            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Draf permohonan berjaya dipadam.',
            ]);
        });
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

    private function generateDraftReferenceNo(): string
    {
        return 'NULL';
    }
}