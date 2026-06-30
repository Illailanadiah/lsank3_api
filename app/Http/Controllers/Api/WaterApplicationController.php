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
    private const REF_PREFIX = 'PBPA';

    public function index(Request $request)
    {
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);

        $applications = LsankApplication::with(['applicant', 'status', 'type', 'waterBody'])
            ->where('user_id', $request->user()->user_id)
            ->where('application_type_id', $typeId)
            ->latest('application_id')
            ->get()
            ->map(fn ($application) => $this->formatApplicationListItem($application, self::TYPE_NAME));

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

            'activity_type_id' => ['nullable', 'integer'],
            'activity_location' => ['nullable', 'string'],
            'longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'operating_days' => ['nullable', 'string', 'max:255'],
            'operating_time' => ['nullable', 'string', 'max:255'],
            'motorized_fee' => ['nullable', 'numeric'],
            'non_motorized_fee' => ['nullable', 'numeric'],
            'activity_details' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $typeId = $this->applicationTypeId(self::TYPE_CODE, self::TYPE_NAME);
        $statusId = $this->applicationStatusId('submitted', 'Dihantar', 2);
        $phoneColumn = $this->applicantPhoneColumn();

        return DB::transaction(function () use ($validated, $user, $typeId, $statusId, $phoneColumn) {
            $applicantData = [
                'user_id' => $user->user_id,
                'applicant_type' => $this->normalizeApplicantType($validated['applicant_type'] ?? null),
                'applicant_name' => $validated['applicant_name'],
                'identity_no' => $validated['identity_no'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => 'active',
            ];

            $applicantData[$phoneColumn] = $validated['phone_no'] ?? $validated['phone'] ?? null;

            $applicant = LsankApplicant::create($applicantData);

            if (!empty($validated['company_name'])) {
                LsankCompany::create([
                    'applicant_id' => $applicant->applicant_id,
                    'company_name' => $validated['company_name'],
                    'registration_no' => $validated['registration_no'] ?? null,
                    'business_address' => $validated['business_address'] ?? null,
                    'business_phone' => $validated['business_phone'] ?? null,
                    'business_email' => $validated['business_email'] ?? null,
                    'responsible_officer_name' => $validated['responsible_officer_name'] ?? null,
                    'responsible_officer_phone' => $validated['responsible_officer_phone'] ?? null,
                ]);
            }

            $application = LsankApplication::create([
                'application_ref_no' => $this->generateReferenceNo(self::REF_PREFIX, $typeId),
                'user_id' => $user->user_id,
                'applicant_id' => $applicant->applicant_id,
                'application_type_id' => $typeId,
                'application_status_id' => $statusId,
                'application_category' => 'new',
                'submitted_at' => now(),
                'remarks' => null,
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
            'applicant.company',
            'status',
            'type',
            'waterBody',
            'documents',
            'reviews',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatApplicationDetail($application, self::TYPE_NAME),
        ]);
    }
}