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

class EffluentApplicationController extends Controller
{
    use HandlesApplicationData;

    private const TYPE_CODE = 'EFFLUENT';
    private const TYPE_NAME = 'Aktiviti Pelepasan Efluen';

    public function index(Request $request)
    {
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

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
            ->map(fn ($application) => $this->formatApplicationListItem(
                $application,
                self::TYPE_NAME
            ));

        return response()->json([
            'success' => true,
            'data' => $applications,
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
            'officers.*.name' => ['required_with:officers', 'string', 'max:255'],
            'officers.*.phone' => ['required_with:officers', 'string', 'max:30'],
            'officers.*.position' => ['required_with:officers', 'string', 'max:255'],

            'service_type_id' => [
                'required',
                'integer',
                'exists:lsank_service_types,service_type_id',
            ],
            'district' => ['required', 'string', 'max:100'],
            'activity_location' => ['nullable', 'string'],
            'longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'composition' => ['nullable', 'string'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'flow_rate' => ['nullable', 'string', 'max:255'],
            'sampling_method' => ['nullable', 'string'],
            'contingency_plan' => ['nullable', 'string'],
            'disposal_method' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
        $statusId = $this->applicationStatusId('submitted', 'Dihantar', 2);
        $phoneColumn = $this->applicantPhoneColumn();

        $serviceType = LsankServiceType::findOrFail(
            $validated['service_type_id']
        );

        return DB::transaction(function () use (
            $validated,
            $user,
            $typeId,
            $statusId,
            $phoneColumn,
            $serviceType
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
                $validated['phone_no'] ?? $validated['phone'] ?? null;

            $applicant = LsankApplicant::create($applicantData);

            if (!empty($validated['company_name'])) {
                $company = LsankCompany::create([
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

                $officers = $validated['officers'] ?? [];

                if (empty($officers) && !empty($validated['responsible_officer_name'])) {
                    $officers[] = [
                        'name' => $validated['responsible_officer_name'],
                        'phone' => $validated['responsible_officer_phone'] ?? '',
                        'position' => $validated['responsible_officer_position'] ?? null,
                    ];
                }

                foreach ($officers as $officer) {
                    if (empty($officer['name'])) {
                        continue;
                    }

                    LsankCompanyOfficer::create([
                        'company_id' => $company->company_id,
                        'officer_name' => $officer['name'],
                        'officer_phone' => $officer['phone'] ?? '',
                        'officer_position' => $officer['position'] ?? null,
                    ]);
                }
            }

            $application = LsankApplication::create([
                'application_ref_no' => $this->generateApplicationFileNo(
                    $serviceType->service_code,
                    $this->districtCode($validated['district'] ?? null)
                ),
                'user_id' => $user->user_id,
                'applicant_id' => $applicant->applicant_id,
                'application_type_id' => $typeId,
                'application_status_id' => $statusId,
                'application_category' => 'new',
                'submitted_at' => now(),
                'remarks' => null,
            ]);

            LsankEffluentApplication::create([
                'application_id' => $application->application_id,
                'service_type_id' => $validated['service_type_id'],
                'activity_location' => $validated['activity_location'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'composition' => $validated['composition'] ?? null,
                'frequency' => $validated['frequency'] ?? null,
                'flow_rate' => $validated['flow_rate'] ?? null,
                'sampling_method' => $validated['sampling_method'] ?? null,
                'contingency_plan' => $validated['contingency_plan'] ?? null,
                'disposal_method' => $validated['disposal_method'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permohonan pelepasan efluen berjaya dihantar.',
                'data' => [
                    'id' => $application->application_id,
                    'application_id' => $application->application_id,
                    'application_no' => $application->application_ref_no,
                    'application_ref_no' => $application->application_ref_no,
                    'service_type_id' => $serviceType->service_type_id,
                    'service_name' => $serviceType->service_name,
                    'service_code' => $serviceType->service_code,
                ],
            ], 201);
        });
    }

    public function show(Request $request, LsankApplication $application)
    {
        if ((int) $application->user_id !== (int) $request->user()->user_id) {
            abort(403, 'Anda tidak dibenarkan melihat permohonan ini.');
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

        return response()->json([
            'success' => true,
            'data' => $this->formatApplicationDetail(
                $application,
                self::TYPE_NAME
            ),
        ]);
    }
}