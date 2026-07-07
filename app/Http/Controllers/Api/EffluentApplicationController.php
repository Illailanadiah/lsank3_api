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
                    'application_ref_no' => 'DRAFT-EFF-' . now()->format('YmdHis'),
                    'user_id' => $user->user_id,
                    'applicant_id' => $applicant->applicant_id,
                    'application_type_id' => $typeId,
                    'application_status_id' => $statusId,
                    'application_category' => 'new',
                    'submitted_at' => null,
                    'remarks' => null,
                ]);
            } else {
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