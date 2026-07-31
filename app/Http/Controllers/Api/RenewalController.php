<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesApplicationData;
use App\Http\Controllers\Controller;
use App\Models\LsankApplicant;
use App\Models\LsankApplication;
use App\Models\LsankCompany;
use App\Models\LsankEffluentApplication;
use App\Models\LsankLicense;
use App\Models\LsankRenewalApplication;
use App\Models\LsankWaterBodyApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RenewalController extends Controller
{
    use HandlesApplicationData;
    /**
     * Number of days before expiry when a user
     * is allowed to begin a renewal.
     */
    private const RENEWAL_WINDOW_DAYS = 60;

    /**
     * Return licences belonging to the logged-in user
     * that are eligible for renewal.
     */
    public function eligible(Request $request)
    {
        $userId = (int) $request->user()->user_id;

        $today = now()->startOfDay();

        $renewalWindowEnd = $today
            ->copy()
            ->addDays(self::RENEWAL_WINDOW_DAYS);

        $query = LsankLicense::query()
            ->with([
                'application',
                'status',
                'renewals.application',
            ])

            /*
             * Only licences connected to an application
             * belonging to the logged-in user.
             *
             * This automatically excludes orphan licences.
             */
            ->whereHas(
                'application',
                function ($applicationQuery) use ($userId) {
                    $applicationQuery->where(
                        'user_id',
                        $userId
                    );
                }
            )

            /*
             * A licence without an expiry date cannot
             * be renewed automatically.
             */
            ->whereNotNull('expiry_date')

            /*
             * Include:
             * - already expired licences; and
             * - licences expiring within 60 days.
             *
             * A lower date limit is intentionally not used
             * because expired licences may have old dates.
             */
            ->whereDate(
                'expiry_date',
                '<=',
                $renewalWindowEnd->toDateString()
            );

        /*
             * Exclude licences that already have an
             * unfinished renewal.
             */

        /*
         * Optional search used by the Flutter search box.
         */
        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where(
                        'license_no',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'file_no',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'holder_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'activity_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'activity_location',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
         * Optional licence-type filter.
         *
         * Accepted examples:
         * water
         * effluent
         * Aktiviti Badan Perairan
         * Aktiviti Pelepasan Efluen
         */
        if ($request->filled('license_type')) {
            $licenseType = strtolower(
                trim(
                    (string) $request->input('license_type')
                )
            );

            if (in_array(
                $licenseType,
                ['water', 'perairan', 'badan perairan'],
                true
            )) {
                $query->where(
                    'license_type',
                    'Aktiviti Badan Perairan'
                );
            } elseif (in_array(
                $licenseType,
                ['effluent', 'efluen'],
                true
            )) {
                $query->where(
                    'license_type',
                    'Aktiviti Pelepasan Efluen'
                );
            } else {
                $query->where(
                    'license_type',
                    $request->input('license_type')
                );
            }
        }

        $licenses = $query
            ->orderBy('expiry_date')
            ->orderBy('license_id')
            ->whereDoesntHave('terminationRequest', function ($query) {
                $query->whereIn('termination_status', [
                    'pending',
                    'approved',
                ]);
            })
            ->get()

            /*
             * Run the final model-level check as a second
             * layer of protection.
             */
            ->filter(function (LsankLicense $license) use ($userId) {
                $activeRenewal = $license->renewals
                    ->first(function ($renewal) {
                        return !in_array(
                            $renewal->renewal_status,
                            [
                                LsankRenewalApplication::STATUS_COMPLETED,
                                LsankRenewalApplication::STATUS_REJECTED,
                                LsankRenewalApplication::STATUS_CANCELLED,
                            ],
                            true
                        );
                    });

                return $activeRenewal !== null
                    || $license->canBeRenewedBy(
                        $userId,
                        self::RENEWAL_WINDOW_DAYS
                    );
            })
            ->values();

        $formattedLicenses = $licenses
            ->map(
                fn(LsankLicense $license) =>
                $this->formatEligibleLicense($license)
            )
            ->values();

        $waterCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->application?->isWaterApplication()
                    ?? false
            )
            ->count();

        $effluentCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->application?->isEffluentApplication()
                    ?? false
            )
            ->count();

        $expiredCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->is_expired
            )
            ->count();

        return response()->json([
            'success' => true,

            'renewal_window_days' =>
            self::RENEWAL_WINDOW_DAYS,

            'summary' => [
                'total' => $licenses->count(),
                'water' => $waterCount,
                'effluent' => $effluentCount,
                'expired' => $expiredCount,
                'expiring' =>
                $licenses->count() - $expiredCount,
            ],

            'licenses' => $formattedLicenses,
        ]);
    }

    /**
     * Start or resume a licence renewal.
     */
    public function start(
        Request $request,
        int $licenseId
    ) {
        $userId = (int) $request->user()->user_id;

        return DB::transaction(function () use (
            $licenseId,
            $userId
        ) {
            /*
         * Lock the licence while creating the renewal.
         * This prevents two rapid button clicks from
         * creating duplicate renewal records.
         */
            $license = LsankLicense::query()
                ->with([
                    'application.applicant.company',
                    'application.waterBody',
                    'application.effluent',
                ])
                ->lockForUpdate()
                ->find($licenseId);

            /*
         * Do not reveal a licence belonging to another user.
         */
            if (
                !$license
                || !$license->application
                || (int) $license->application->user_id !== $userId
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesen tidak dijumpai.',
                ], 404);
            }

            /*
         * If the user already started a renewal,
         * return the same draft instead of creating
         * another application.
         */
            $existingRenewal =
                LsankRenewalApplication::query()
                ->with('application')
                ->where(
                    'license_id',
                    $license->license_id
                )
                ->whereNotIn(
                    'renewal_status',
                    [
                        LsankRenewalApplication::STATUS_COMPLETED,
                        LsankRenewalApplication::STATUS_REJECTED,
                        LsankRenewalApplication::STATUS_CANCELLED,
                    ]
                )
                ->lockForUpdate()
                ->latest('renewal_id')
                ->first();

            if ($existingRenewal) {
                $existingApplication =
                    $existingRenewal->application;

                if (
                    !$existingApplication
                    || (int) $existingApplication->user_id !== $userId
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                        'Rekod pembaharuan tidak sah.',
                    ], 409);
                }

                return $this->renewalStartResponse(
                    renewal: $existingRenewal,
                    application: $existingApplication,
                    license: $license,
                    resumed: true,
                );
            }

            /*
         * Perform the final eligibility check.
         */
            if (
                !$license->canBeRenewedBy(
                    $userId,
                    self::RENEWAL_WINDOW_DAYS
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Lesen ini belum layak untuk diperbaharui.',
                ], 422);
            }

            $sourceApplication = $license->application;

            /*
         * Create a separate applicant record so editing
         * the renewal does not modify historical data in
         * the original approved application.
         */
            $newApplicant =
                $this->createRenewalApplicant(
                    $sourceApplication,
                    $userId
                );

            $draftData =
                $this->prepareRenewalDraftData(
                    $sourceApplication,
                    $license
                );

            $draftStatusId =
                $this->applicationStatusId(
                    'draft',
                    'Draf',
                    1
                );

            /*
         * Create a new application using data from the
         * original approved application.
         */
            $newApplication = LsankApplication::create([
                'application_ref_no' =>
                $this->generateRenewalDraftReferenceNo(
                    $userId
                ),

                'user_id' => $userId,

                'applicant_id' =>
                $newApplicant->applicant_id,

                'application_type_id' =>
                $sourceApplication->application_type_id,

                'application_status_id' =>
                $draftStatusId,

                'application_category' =>
                'renewal',

                'application_status' =>
                LsankApplication::STATUS_DRAF,

                'payment_status' =>
                LsankApplication::PAYMENT_BELUM_BAYAR,

                'current_step' => 0,

                'submitted_at' => null,
                'review_data' => null,
                'submitted_data' => null,

                'remarks' =>
                'Draf pembaharuan bagi lesen ' .
                    $license->license_no . '.',

                'draft_data' => $draftData,

                /*
                * Main application information.
                */
                /*
 * Maklumat utama dan Borang A sahaja.
 */
                'applicant_name' =>
                $sourceApplication->applicant_name,

                'business_name' =>
                $sourceApplication->business_name,

                'phone' =>
                $sourceApplication->phone,

                'email' =>
                $sourceApplication->email,

                'license_type' =>
                $sourceApplication->license_type,

                'application_type' =>
                $sourceApplication->application_type,

                /*
                * Data aktiviti lama tidak disalin.
                */
                'activity_type' => null,
                'activity_name' => null,
                'district' => null,
                'activity_location' => null,
                'longitude' => null,
                'latitude' => null,
                'activity_details' => null,

                /*
                * Applicant information.
                */
                'applicant_type' =>
                $sourceApplication->applicant_type,

                'identity_no' =>
                $sourceApplication->identity_no,

                'phone_no' =>
                $sourceApplication->phone_no,

                'address' =>
                $sourceApplication->address,

                /*
                * Company information.
                */
                'company_name' =>
                $sourceApplication->company_name,

                'registration_no' =>
                $sourceApplication->registration_no,

                'business_address' =>
                $sourceApplication->business_address,

                'business_phone' =>
                $sourceApplication->business_phone,

                'business_email' =>
                $sourceApplication->business_email,

                'responsible_officer_name' =>
                $sourceApplication
                    ->responsible_officer_name,

                'responsible_officer_phone' =>
                $sourceApplication
                    ->responsible_officer_phone,

                'responsible_officer_position' =>
                $sourceApplication
                    ->responsible_officer_position,

                'officers' =>
                $sourceApplication->officers ?? [],

                /*
                * Activity-specific information.
                */
                'activity_type_id' => null,
                'operating_days' => null,
                'operating_time' => null,
                'recreation_details' => [],
            ]);

            /*
            * Connect the old licence with the new
            * renewal application.
            */
            $renewal =
                LsankRenewalApplication::create([
                    'application_id' =>
                    $newApplication->application_id,

                    'license_id' =>
                    $license->license_id,

                    'old_expiry_date' =>
                    $license->expiry_date,

                    'new_expiry_date' =>
                    null,

                    'renewal_status' =>
                    LsankRenewalApplication::STATUS_DRAFT,
                ]);

            return $this->renewalStartResponse(
                renewal: $renewal,
                application: $newApplication,
                license: $license,
                resumed: false,
            );
        });
    }

    /**
     * Prepare one licence for the Flutter renewal list.
     */
    private function formatEligibleLicense(
        LsankLicense $license
    ): array {
        $application = $license->application;

        $isExpired = $license->is_expired;
        $activeRenewal = $license->renewals
            ->first(function ($renewal) {
                return !in_array(
                    $renewal->renewal_status,
                    [
                        LsankRenewalApplication::STATUS_COMPLETED,
                        LsankRenewalApplication::STATUS_REJECTED,
                        LsankRenewalApplication::STATUS_CANCELLED,
                    ],
                    true
                );
            });

        $hasActiveRenewal = $activeRenewal !== null;

        return [
            'license_id' =>
            (int) $license->license_id,

            'application_id' =>
            (int) $license->application_id,

            'file_no' =>
            (string) ($license->file_no ?? ''),

            'license_no' =>
            (string) ($license->license_no ?? ''),

            'holder_name' =>
            (string) ($license->holder_name ?? ''),

            'license_type' =>
            (string) ($license->license_type ?? ''),

            'application_type' =>
            (string) (
                $application?->application_type
                ?? (
                    $application?->isEffluentApplication()
                    ? 'effluent'
                    : 'water'
                )
            ),

            'applicant_type' =>
            (string) (
                $application?->applicant_type
                ?? 'Individu'
            ),

            'activity' =>
            (string) ($license->activity_name ?? ''),

            'location' =>
            (string) (
                $license->activity_location
                ?: $application?->district
                ?: '-'
            ),

            'start_date' =>
            $license->start_date?->format('d/m/Y'),

            'end_date' =>
            $license->expiry_date?->format('d/m/Y'),

            'status' =>
            $hasActiveRenewal
                ? 'Dalam Pembaharuan'
                : (
                    $isExpired
                    ? 'Tamat Tempoh'
                    : 'Akan Tamat'
                ),

            'renewal_state' =>
            $hasActiveRenewal
                ? 'in_progress'
                : (
                    $isExpired
                    ? 'expired'
                    : 'expiring'
                ),

            'renewal_id' =>
            $activeRenewal
                ? (int) $activeRenewal->renewal_id
                : null,

            'renewal_application_id' =>
            $activeRenewal
                ? (int) $activeRenewal->application_id
                : null,

            'has_active_renewal' =>
            $hasActiveRenewal,

            'can_renew' => true,

            'days_until_expiry' =>
            $license->days_until_expiry,

            'can_renew' => true,
        ];
    }
    /**
     * Create a separate applicant for the renewal draft.
     */
    private function createRenewalApplicant(
        LsankApplication $sourceApplication,
        int $userId
    ): LsankApplicant {
        $sourceApplicant =
            $sourceApplication->applicant;

        $newApplicant = LsankApplicant::create([
            'user_id' => $userId,

            'applicant_type' =>
            $sourceApplicant?->applicant_type
                ?? $this->normalizeApplicantType(
                    $sourceApplication->applicant_type
                ),

            'applicant_name' =>
            $sourceApplicant?->applicant_name
                ?? $sourceApplication->applicant_name
                ?? '-',

            'identity_no' =>
            $sourceApplicant?->identity_no
                ?? $sourceApplication->identity_no,

            'email' =>
            $sourceApplicant?->email
                ?? $sourceApplication->email,

            'phone_no' =>
            $sourceApplicant?->phone_no
                ?? $sourceApplication->phone_no
                ?? $sourceApplication->phone,

            'address' =>
            $sourceApplicant?->address
                ?? $sourceApplication->address,

            'status' => 'active',
        ]);

        $sourceCompany =
            $sourceApplicant?->company;

        $companyName = trim(
            (string) (
                $sourceCompany?->company_name
                ?? $sourceApplication->company_name
                ?? $sourceApplication->business_name
                ?? ''
            )
        );

        if ($companyName !== '') {
            LsankCompany::create([
                'applicant_id' =>
                $newApplicant->applicant_id,

                'company_name' =>
                $companyName,

                'registration_no' =>
                $sourceCompany?->registration_no
                    ?? $sourceApplication->registration_no,

                'business_address' =>
                $sourceCompany?->business_address
                    ?? $sourceApplication->business_address,

                'business_phone' =>
                $sourceCompany?->business_phone
                    ?? $sourceApplication->business_phone,

                'business_email' =>
                $sourceCompany?->business_email
                    ?? $sourceApplication->business_email,

                'responsible_officer_name' =>
                $sourceCompany?->responsible_officer_name
                    ?? $sourceApplication
                    ->responsible_officer_name,

                'responsible_officer_phone' =>
                $sourceCompany?->responsible_officer_phone
                    ?? $sourceApplication
                    ->responsible_officer_phone,
            ]);
        }

        return $newApplicant;
    }

    /**
     * Copy previous form information into the new draft.
     *
     * Payment, review, submission and document upload
     * states are intentionally reset.
     */
    private function prepareRenewalDraftData(
        LsankApplication $sourceApplication,
        LsankLicense $license
    ): array {
        /*
     * Pembaharuan hanya membawa semula Borang A.
     *
     * Data aktiviti, borang teknikal dan fail lama
     * tidak disalin ke dalam draf pembaharuan.
     */
        return [
            'step' => 0,
            'current_step' => 0,
            'completed_steps' => [],
            'agree_terms' => false,

            /*
         * Maklumat Borang A – pemohon.
         */
            'applicant_type' =>
            $sourceApplication->applicant_type,

            /*
         * Jangan salin aktiviti lama sebagai data borang.
         * selected_activities hanya digunakan untuk
         * menentukan struktur borang yang perlu dipaparkan.
         */
            'selected_activities' =>
            $this->resolveRenewalSelectedActivities(
                $sourceApplication,
                $license
            ),

            'original_selected_activities' =>
            $this->resolveRenewalSelectedActivities(
                $sourceApplication,
                $license
            ),

            /*
         * Pastikan dokumen lama kosong.
         */
            'uploaded_documents' => [],

            'documents' => [
                'uploaded_keys' => [],
            ],

            /*
         * Metadata pembaharuan.
         */
            'is_renewal' => true,

            'prefill_scope' => 'form_a',

            'load_uploads' => false,

            'renewal_license_id' =>
            (int) $license->license_id,

            'renewal_license_no' =>
            (string) $license->license_no,

            'original_application_id' =>
            (int) $sourceApplication->application_id,

            'meta' => [
                'step' => 0,
                'current_step' => 0,
                'is_renewal' => true,
                'prefill_scope' => 'form_a',
                'load_uploads' => false,

                'renewal_license_id' =>
                (int) $license->license_id,

                'renewal_license_no' =>
                (string) $license->license_no,

                'original_application_id' =>
                (int) $sourceApplication->application_id,
            ],
        ];
    }

    private function resolveRenewalSelectedActivities(
        LsankApplication $sourceApplication,
        LsankLicense $license
    ): array {
        $activityName = trim(
            (string) (
                $license->activity_name
                ?: $sourceApplication->activity_name
                ?: $sourceApplication->activity_details
            )
        );

        if ($activityName === '') {
            return [];
        }

        return [$activityName];
    }

    /**
     * Copy the Water or Effluent child record.
     */
    private function copyActivityRecord(
        LsankApplication $sourceApplication,
        LsankApplication $newApplication
    ): void {
        if (
            $sourceApplication->isEffluentApplication()
            && $sourceApplication->effluent
        ) {
            $source = $sourceApplication->effluent;

            LsankEffluentApplication::create([
                'application_id' =>
                $newApplication->application_id,

                'service_type_id' =>
                $source->service_type_id,

                'activity_location' =>
                $source->activity_location,

                'longitude' =>
                $source->longitude,

                'latitude' =>
                $source->latitude,

                'composition' =>
                $source->composition,

                'frequency' =>
                $source->frequency,

                'flow_rate' =>
                $source->flow_rate,

                'sampling_method' =>
                $source->sampling_method,

                'contingency_plan' =>
                $source->contingency_plan,

                'disposal_method' =>
                $source->disposal_method,
            ]);

            return;
        }

        if (
            $sourceApplication->isWaterApplication()
            && $sourceApplication->waterBody
        ) {
            $source = $sourceApplication->waterBody;

            LsankWaterBodyApplication::create([
                'application_id' =>
                $newApplication->application_id,

                'activity_type_id' =>
                $source->activity_type_id,

                'activity_location' =>
                $source->activity_location,

                'longitude' =>
                $source->longitude,

                'latitude' =>
                $source->latitude,

                'operating_days' =>
                $source->operating_days,

                'operating_time' =>
                $source->operating_time,

                'motorized_fee' =>
                $source->motorized_fee,

                'non_motorized_fee' =>
                $source->non_motorized_fee,

                'activity_details' =>
                $source->activity_details,

                'draft_data' =>
                $source->draft_data,
            ]);
        }
    }

    /**
     * Generate a unique temporary reference number.
     */
    private function generateRenewalDraftReferenceNo(
        int $userId
    ): string {
        do {
            $referenceNo =
                'DRAF-RNW-' .
                $userId . '-' .
                now()->format('YmdHis') . '-' .
                Str::upper(Str::random(4));
        } while (
            LsankApplication::where(
                'application_ref_no',
                $referenceNo
            )->exists()
        );

        return $referenceNo;
    }

    /**
     * Return navigation information to Flutter.
     */
    private function renewalStartResponse(
        LsankRenewalApplication $renewal,
        LsankApplication $application,
        LsankLicense $license,
        bool $resumed
    ) {
        $applicationType =
            $application->isEffluentApplication()
            ? 'effluent'
            : 'water';

        $path =
            $applicationType === 'effluent'
            ? '/applications/effluent/form'
            : '/applications/water/form';

        return response()->json([
            'success' => true,

            'message' =>
            $resumed
                ? 'Draf pembaharuan sedia ada diteruskan.'
                : 'Draf pembaharuan berjaya dicipta.',

            'resumed' => $resumed,

            'data' => [
                'renewal_id' =>
                (int) $renewal->renewal_id,

                'renewal_status' =>
                (string) $renewal->renewal_status,

                'license_id' =>
                (int) $license->license_id,

                'license_no' =>
                (string) $license->license_no,

                'old_application_id' =>
                (int) $license->application_id,

                'application_id' =>
                (int) $application->application_id,

                'application_ref_no' =>
                (string) $application
                    ->application_ref_no,

                'application_category' =>
                (string) $application
                    ->application_category,

                'application_type' =>
                $applicationType,

                'selected_activities' =>
                data_get(
                    $application->draft_data,
                    'selected_activities',
                    []
                ),
            ],

            'navigation' => [
                'path' => $path,

                'query' => [
                    'applicationId' =>
                    (string) $application
                        ->application_id,

                    'resumeDraft' => 'true',

                    'applicantType' =>
                    (string) (
                        $application->applicant_type
                        ?: 'Individu'
                    ),

                    'renewalId' =>
                    (string) $renewal->renewal_id,

                    'licenseId' =>
                    (string) $license->license_id,

                    'isRenewal' => 'true',
                ],
            ],
        ]);
    }
}
