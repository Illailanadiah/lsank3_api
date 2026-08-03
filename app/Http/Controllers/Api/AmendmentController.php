<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesApplicationData;
use App\Http\Controllers\Controller;
use App\Models\LsankAmendmentApplication;
use App\Models\LsankApplicant;
use App\Models\LsankApplication;
use App\Models\LsankCompany;
use App\Models\LsankLicense;
use App\Models\LsankWaterBodyApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AmendmentController extends Controller
{
    use HandlesApplicationData;

    private const CLOSED_STATUSES = [
        'completed',
        'rejected',
        'cancelled',
    ];

    /**
     * Cipta atau sambung draf pindaan lesen.
     */
    public function start(
        Request $request,
        int $licenseId
    ) {
        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        return DB::transaction(function () use (
            $licenseId,
            $userId
        ) {
            $license = LsankLicense::query()
                ->with([
                    'application.applicant.company',
                    'application.waterBody',
                    'application.documents',
                ])
                ->lockForUpdate()
                ->find($licenseId);

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

            $sourceApplication = $license->application;

            if (!$sourceApplication->isWaterApplication()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Pindaan ini hanya tersedia untuk '
                        . 'permohonan Aktiviti Badan Perairan.',
                ], 422);
            }

            /*
             * Sambung draf lama supaya dua klik tidak
             * menghasilkan dua permohonan pindaan.
             */
            $existingAmendment =
                LsankAmendmentApplication::query()
                ->with('application')
                ->where(
                    'license_id',
                    $license->license_id
                )
                ->whereNotIn(
                    'status',
                    self::CLOSED_STATUSES
                )
                ->latest('amendment_id')
                ->lockForUpdate()
                ->first();

            if ($existingAmendment) {
                $existingApplication =
                    $existingAmendment->application;

                if (
                    !$existingApplication
                    || (int) $existingApplication->user_id
                    !== $userId
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                        'Rekod pindaan lesen tidak sah.',
                    ], 409);
                }

                return $this->startResponse(
                    amendment: $existingAmendment,
                    application: $existingApplication,
                    license: $license,
                    resumed: true,
                );
            }

            /*
             * Salin applicant supaya pindaan tidak
             * mengubah rekod applicant lama.
             */
            $newApplicant = $this->copyApplicant(
                $sourceApplication,
                $userId
            );

            /*
             * Salin semua nilai borang lama.
             */
            $draftData = $this->prepareDraftData(
                $sourceApplication,
                $license
            );

            /*
             * Clone application asal.
             */
            $newApplication =
                $sourceApplication->replicate();

            $newApplication->application_ref_no =
                $this->generateDraftReferenceNo(
                    $userId
                );

            $newApplication->user_id = $userId;

            $newApplication->applicant_id =
                $newApplicant->applicant_id;

            $newApplication->application_category =
                'amendment';

            $newApplication->application_status_id =
                $this->applicationStatusId(
                    'draft',
                    'Draf',
                    1
                );

            $newApplication->application_status =
                LsankApplication::STATUS_DRAF;

            $newApplication->payment_status =
                LsankApplication::PAYMENT_BELUM_BAYAR;

            $newApplication->current_step = 0;
            $newApplication->submitted_at = null;
            $newApplication->review_data = null;
            $newApplication->submitted_data = null;

            $newApplication->remarks =
                'Draf pindaan bagi lesen '
                . $license->license_no
                . '.';

            $newApplication->draft_data =
                $draftData;

            $newApplication->save();

            /*
             * Salin rekod teknikal badan perairan.
             */
            $this->copyWaterBody(
                $sourceApplication,
                $newApplication
            );

            /*
             * Cipta rekod pindaan.
             */
            $amendment =
                LsankAmendmentApplication::create([
                    'application_id' =>
                    $newApplication->application_id,

                    'license_id' =>
                    $license->license_id,

                    /*
                     * Nilai akhir akan dikemaskini selepas
                     * sistem membandingkan data lama/baharu.
                     */
                    'amendment_type' =>
                    'activity',

                    'old_information' => [
                        'source_application_id' =>
                        $sourceApplication
                            ->application_id,

                        'application' =>
                        $sourceApplication
                            ->withoutRelations()
                            ->toArray(),

                        'draft_data' =>
                        $sourceApplication
                            ->draft_data ?? [],

                        'water_body' =>
                        $sourceApplication
                            ->waterBody
                            ?->toArray(),
                    ],

                    'new_information' => [
                        'meta' => [
                            'form_a_edit_enabled' =>
                            false,

                            'form_a_changed' =>
                            false,

                            'activity_changed' =>
                            false,

                            'processing_fee' =>
                            150,

                            'information_amendment_fee' =>
                            0,

                            'incremental_charge_fee' =>
                            0,
                        ],
                    ],

                    'reason' => null,
                    'status' => 'draft',
                ]);

            /*
             * Simpan amendment_id dalam draft_data supaya
             * Flutter boleh mendapatkannya selepas refresh.
             */
            $draftData['amendment_id'] =
                (int) $amendment->amendment_id;

            $draftData['amendment'] = [
                'amendment_id' =>
                (int) $amendment->amendment_id,

                'license_id' =>
                (int) $license->license_id,

                'is_amendment' => true,

                'form_a_edit_enabled' =>
                false,
            ];

            $draftData['meta'] = array_merge(
                is_array($draftData['meta'] ?? null)
                    ? $draftData['meta']
                    : [],
                [
                    'is_amendment' => true,

                    'amendment_id' =>
                    (int) $amendment
                        ->amendment_id,

                    'license_id' =>
                    (int) $license->license_id,

                    'original_application_id' =>
                    (int) $sourceApplication
                        ->application_id,

                    'form_a_edit_enabled' =>
                    false,
                ]
            );

            $newApplication->forceFill([
                'draft_data' => $draftData,
            ])->save();

            return $this->startResponse(
                amendment: $amendment,
                application: $newApplication,
                license: $license,
                resumed: false,
            );
        });
    }

    /**
     * Buka Borang A untuk diedit.
     */
    public function enableFormA(
        Request $request,
        int $amendmentId
    ) {
        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        return DB::transaction(function () use (
            $amendmentId,
            $userId
        ) {
            $amendment =
                LsankAmendmentApplication::query()
                ->with([
                    'application',
                    'license.application',
                ])
                ->lockForUpdate()
                ->find($amendmentId);

            if (
                !$amendment
                || !$amendment->application
                || !$amendment->license
                || (int) $amendment
                    ->application
                    ->user_id !== $userId
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Permohonan pindaan tidak dijumpai.',
                ], 404);
            }

            if (
                in_array(
                    strtolower(
                        (string) $amendment->status
                    ),
                    self::CLOSED_STATUSES,
                    true
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Permohonan pindaan ini telah ditutup.',
                ], 422);
            }

            $newInformation = is_array(
                $amendment->new_information
            )
                ? $amendment->new_information
                : [];

            $newInformation['meta'] = array_merge(
                is_array(
                    $newInformation['meta'] ?? null
                )
                    ? $newInformation['meta']
                    : [],
                [
                    'form_a_edit_enabled' => true,

                    'information_amendment_fee' => 50,

                    'form_a_fee_triggered_at' =>
                    now()->toDateTimeString(),
                ]
            );

            $amendment->forceFill([
                'new_information' =>
                $newInformation,
            ])->save();

            /*
             * Simpan juga dalam draft application.
             */
            $application =
                $amendment->application;

            $draftData = is_array(
                $application->draft_data
            )
                ? $application->draft_data
                : [];

            $draftData['is_amendment'] = true;

            $draftData['amendment_id'] =
                (int) $amendment->amendment_id;

            $draftData['amendment'] = array_merge(
                is_array(
                    $draftData['amendment'] ?? null
                )
                    ? $draftData['amendment']
                    : [],
                [
                    'amendment_id' =>
                    (int) $amendment
                        ->amendment_id,

                    'license_id' =>
                    (int) $amendment
                        ->license_id,

                    'is_amendment' => true,

                    'form_a_edit_enabled' =>
                    true,

                    'information_amendment_fee' =>
                    50,
                ]
            );

            $draftData['meta'] = array_merge(
                is_array($draftData['meta'] ?? null)
                    ? $draftData['meta']
                    : [],
                [
                    'is_amendment' => true,

                    'amendment_id' =>
                    (int) $amendment
                        ->amendment_id,

                    'form_a_edit_enabled' =>
                    true,

                    'information_amendment_fee' =>
                    50,
                ]
            );

            $application->forceFill([
                'draft_data' => $draftData,
            ])->save();

            return response()->json([
                'success' => true,

                'message' =>
                'Borang A telah dibuka untuk pindaan.',

                'data' => [
                    'amendment_id' =>
                    (int) $amendment
                        ->amendment_id,

                    'application_id' =>
                    (int) $application
                        ->application_id,

                    'form_a_edit_enabled' =>
                    true,

                    'information_amendment_fee' =>
                    50,
                ],
            ]);
        });
    }

    private function copyApplicant(
        LsankApplication $sourceApplication,
        int $userId
    ): LsankApplicant {
        $sourceApplicant =
            $sourceApplication->applicant;

        if ($sourceApplicant) {
            $newApplicant =
                $sourceApplicant->replicate();

            $newApplicant->user_id = $userId;
            $newApplicant->save();
        } else {
            $newApplicant = LsankApplicant::create([
                'user_id' => $userId,

                'applicant_type' =>
                $sourceApplication
                    ->applicant_type,

                'applicant_name' =>
                $sourceApplication
                    ->applicant_name
                    ?? '-',

                'identity_no' =>
                $sourceApplication
                    ->identity_no,

                'email' =>
                $sourceApplication
                    ->email,

                'phone_no' =>
                $sourceApplication
                    ->phone_no
                    ?? $sourceApplication
                    ->phone,

                'address' =>
                $sourceApplication
                    ->address,

                'status' => 'active',
            ]);
        }

        $sourceCompany =
            $sourceApplicant?->company;

        if ($sourceCompany) {
            $newCompany =
                $sourceCompany->replicate();

            $newCompany->applicant_id =
                $newApplicant->applicant_id;

            $newCompany->save();
        }

        return $newApplicant;
    }

    private function copyWaterBody(
        LsankApplication $sourceApplication,
        LsankApplication $newApplication
    ): void {
        if (!$sourceApplication->waterBody) {
            return;
        }

        $newWaterBody =
            $sourceApplication
            ->waterBody
            ->replicate();

        $newWaterBody->application_id =
            $newApplication->application_id;

        $newWaterBody->save();
    }

    private function prepareDraftData(
        LsankApplication $sourceApplication,
        LsankLicense $license
    ): array {
        $draftData = is_array(
            $sourceApplication->draft_data
        )
            ? $sourceApplication->draft_data
            : [];

        $selectedActivities =
            $draftData['original_selected_activities']
            ?? $draftData['selected_activities']
            ?? [];

        if (
            !is_array($selectedActivities)
            || empty($selectedActivities)
        ) {
            $activityName = trim(
                (string) (
                    $sourceApplication->activity_name
                    ?: $sourceApplication->activity_details
                    ?: $license->activity_name
                )
            );

            $selectedActivities =
                $activityName !== ''
                ? [$activityName]
                : [];
        }

        $draftData['step'] = 0;
        $draftData['current_step'] = 0;
        $draftData['completed_steps'] = [];
        $draftData['agree_terms'] = false;

        $draftData['selected_activities'] =
            $selectedActivities;

        $draftData['original_selected_activities'] =
            $selectedActivities;

        $draftData['is_amendment'] = true;

        $draftData['license_id'] =
            (int) $license->license_id;

        $draftData['original_application_id'] =
            (int) $sourceApplication
                ->application_id;

        $draftData['meta'] = array_merge(
            is_array($draftData['meta'] ?? null)
                ? $draftData['meta']
                : [],
            [
                'is_amendment' => true,

                'license_id' =>
                (int) $license->license_id,

                'original_application_id' =>
                (int) $sourceApplication
                    ->application_id,

                'form_a_edit_enabled' =>
                false,
            ]
        );

        return $draftData;
    }

    private function generateDraftReferenceNo(
        int $userId
    ): string {
        do {
            $referenceNo =
                'DRAF-AMD-'
                . $userId
                . '-'
                . now()->format('YmdHis')
                . '-'
                . Str::upper(
                    Str::random(4)
                );
        } while (
            LsankApplication::query()
            ->where(
                'application_ref_no',
                $referenceNo
            )
            ->exists()
        );

        return $referenceNo;
    }

    private function startResponse(
        LsankAmendmentApplication $amendment,
        LsankApplication $application,
        LsankLicense $license,
        bool $resumed
    ) {
        $selectedActivities = data_get(
            $application->draft_data,
            'selected_activities',
            []
        );

        return response()->json([
            'success' => true,

            'message' => $resumed
                ? 'Draf pindaan sedia ada diteruskan.'
                : 'Draf pindaan berjaya dicipta.',

            'resumed' => $resumed,

            'data' => [
                'amendment_id' =>
                (int) $amendment
                    ->amendment_id,

                'amendment_status' =>
                (string) $amendment
                    ->status,

                'license_id' =>
                (int) $license
                    ->license_id,

                'license_no' =>
                (string) $license
                    ->license_no,

                'old_application_id' =>
                (int) $license
                    ->application_id,

                'application_id' =>
                (int) $application
                    ->application_id,

                'application_ref_no' =>
                (string) $application
                    ->application_ref_no,

                'application_category' =>
                'amendment',

                'application_type' =>
                'water',

                'applicant_type' =>
                (string) (
                    $application
                    ->applicant_type
                    ?: 'Individu'
                ),

                'selected_activities' =>
                is_array($selectedActivities)
                    ? $selectedActivities
                    : [],

                'form_a_edit_enabled' =>
                (bool) data_get(
                    $application->draft_data,
                    'amendment.form_a_edit_enabled',
                    false
                ),
            ],

            'navigation' => [
                'path' =>
                '/applications/water/form',

                'query' => [
                    'applicationId' =>
                    (string) $application
                        ->application_id,

                    'resumeDraft' => 'true',

                    'applicantType' =>
                    (string) (
                        $application
                        ->applicant_type
                        ?: 'Individu'
                    ),

                    'amendmentId' =>
                    (string) $amendment
                        ->amendment_id,

                    'licenseId' =>
                    (string) $license
                        ->license_id,

                    'isAmendment' => 'true',
                ],
            ],
        ]);
    }
}
