<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use App\Models\LsankLicense;
use App\Models\LsankLicenseStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use App\Models\LsankLicenseTerminationRequest;
use App\Models\LsankInvoice;
use App\Services\NoticeRestrictionService;
use App\Models\LsankNoticeRestriction;

class LicenseController extends Controller
{
    /**
     * Return all generated licenses.
     */
    public function index(Request $request)
    {
        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak sah.',
            ], 401);
        }

        $applicationIds = LsankApplication::query()
            ->where('user_id', $userId)
            ->pluck('application_id');

        $query = LsankLicense::query()
            ->with([
                'application',
                'status',
                'terminationRequest',
            ])
            ->whereIn('application_id', $applicationIds)
            ->latest('generated_at')
            ->latest('license_id');

        if ($request->filled('status')) {
            $status = trim((string) $request->input('status'));

            $query->whereHas('status', function ($statusQuery) use ($status) {
                $statusQuery
                    ->where('status_code', $status)
                    ->orWhere('status_name', $status);
            });
        }

        if ($request->filled('license_type')) {
            $query->where(
                'license_type',
                trim((string) $request->input('license_type'))
            );
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('license_no', 'like', "%{$search}%")
                    ->orWhere('file_no', 'like', "%{$search}%")
                    ->orWhere('holder_name', 'like', "%{$search}%")
                    ->orWhere('license_type', 'like', "%{$search}%")
                    ->orWhere('activity_name', 'like', "%{$search}%")
                    ->orWhere('activity_location', 'like', "%{$search}%");
            });
        }

        $licenses = $query->get()->map(
            fn(LsankLicense $license) => $this->formatLicense($license)
        );

        return response()->json([
            'success' => true,
            'licenses' => $licenses,
        ]);
    }

    /**
     * Return one license.
     */
    public function show(
        Request $request,
        LsankLicense $license
    )
    {
        if (!$this->canAccessLicense($request->user(), $license)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan melihat lesen ini.',
            ], 403);
        }

        $license->load([
            'application.type',
            'application.applicantType',
            'application.districtMaster',
            'application.category.applicationType',
            'application.waterBody.activityType',
            'application.effluent.serviceType',
            'status',
            'terminationRequest'
        ]);

        return response()->json([
            'success' => true,
            'license' => $this->formatLicense($license),
        ]);
    }

    public function adminIndex(Request $request)
{
    if (!$this->canViewAllLicenses($request->user())) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak dibenarkan melihat semua lesen.',
        ], 403);
    }

    $query = LsankLicense::query()
        ->with([
            'application',
            'status',
            'terminationRequest',
        ])
        ->latest('generated_at')
        ->latest('license_id');

    if ($request->filled('status')) {
        $status = trim((string) $request->input('status'));

        $query->whereHas('status', function ($statusQuery) use ($status) {
            $statusQuery
                ->where('status_code', $status)
                ->orWhere('status_name', $status);
        });
    }

    if ($request->filled('license_type')) {
        $query->where(
            'license_type',
            trim((string) $request->input('license_type'))
        );
    }

    if ($request->filled('search')) {
        $search = trim((string) $request->input('search'));

        $query->where(function ($builder) use ($search) {
            $builder
                ->where('license_no', 'like', "%{$search}%")
                ->orWhere('file_no', 'like', "%{$search}%")
                ->orWhere('holder_name', 'like', "%{$search}%")
                ->orWhere('license_type', 'like', "%{$search}%")
                ->orWhere('activity_name', 'like', "%{$search}%")
                ->orWhere('activity_location', 'like', "%{$search}%");
        });
    }

    $licenses = $query->get()->map(
        fn (LsankLicense $license) =>
            $this->formatLicense($license)
    );

    return response()->json([
        'success' => true,
        'licenses' => $licenses,
        'total' => $licenses->count(),
    ]);
}

private function canViewAllLicenses(mixed $user): bool
{
    if ($user === null) {
        return false;
    }

    $email = strtolower(
        trim((string) data_get($user, 'email', ''))
    );

    if ($email === 'admin@lsank.gov.my') {
        return true;
    }

    $roleCandidates = [
        data_get($user, 'role.role_name'),
        data_get($user, 'role.name'),
        data_get($user, 'role.role_code'),
        data_get($user, 'role.code'),
        data_get($user, 'role_name'),
        data_get($user, 'role_code'),
        data_get($user, 'user_role'),
        data_get($user, 'user_type'),
        is_string(data_get($user, 'role'))
            ? data_get($user, 'role')
            : null,
    ];

    foreach ($roleCandidates as $candidate) {
        if (!is_scalar($candidate)) {
            continue;
        }

        $role = strtolower(
            trim((string) $candidate)
        );

        $role = str_replace(
            ['_', '-'],
            ' ',
            $role
        );

        $role = preg_replace(
            '/\s+/',
            ' ',
            $role
        );

        if (
            in_array(
                $role,
                [
                    'admin',
                    'administrator',
                    'pentadbiran',
                    'ketua pengarah',
                    'ketua unit efluen',
                    'ketua bahagian efluen',
                    'teknikal efluen',
                    'ketua unit badan perairan',
                    'ketua bahagian badan perairan',
                    'teknikal badan perairan',
                    'penguatkuasa',
                    'kewangan',
                    'pegawai undang undang',
                    'penguatkuasa perundangan',
                    'penolong pegawai undang undang',
                ],
                true
            )
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Admin/staff may access every licence. A normal user may only access a
 * licence that belongs to their original application.
 */
private function canAccessLicense(
    mixed $user,
    LsankLicense $license
): bool {
    if ($user === null) {
        return false;
    }

    if ($this->canViewAllLicenses($user)) {
        return true;
    }

    $userId = (int) (
        data_get($user, 'user_id')
        ?? data_get($user, 'id')
        ?? 0
    );

    return $userId > 0 && $license->belongsToUser($userId);
}
    /**
     * Applicant submits a license termination request.
     *
     * The license remains active until the Director
     * approves the request.
     */
    public function requestTermination(
        Request $request,
        LsankLicense $license
    ) {
        $validated = $request->validate([
            'application_id' => [
                'nullable',
                'integer',
            ],
            'application_type' => [
                'nullable',
                'string',
                'max:30',
            ],
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        if (
            $userId <= 0 ||
            !$license->belongsToUser($userId)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Anda tidak dibenarkan memohon '
                    . 'penamatan lesen ini.',
            ], 403);
        }

        $license->loadMissing([
            'application.type',
            'application.applicantType',
            'application.districtMaster',
            'application.category.applicationType',
            'application.waterBody.activityType',
            'application.effluent.serviceType',
            'status',
            'terminationRequest',
        ]);

        if (
            isset($validated['application_id']) &&
            (int) $validated['application_id'] !==
            (int) $license->application_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'ID permohonan tidak sepadan '
                    . 'dengan lesen ini.',
            ], 422);
        }

        if ($license->is_expired) {
            return response()->json([
                'success' => false,
                'message' =>
                'Lesen ini telah tamat dan tidak boleh '
                    . 'dimohon untuk penamatan.',
            ], 422);
        }

        $statusValue = strtolower(
            trim((string) (
                $license->status?->status_code
                ?? $license->status?->status_name
                ?? ''
            ))
        );

        $licenseAlreadyInactive =
            str_contains($statusValue, 'expired') ||
            str_contains($statusValue, 'inactive') ||
            str_contains($statusValue, 'terminated') ||
            str_contains($statusValue, 'tidak aktif') ||
            str_contains($statusValue, 'tamat');

        if ($licenseAlreadyInactive) {
            return response()->json([
                'success' => false,
                'message' =>
                'Lesen ini sudah tidak aktif atau telah tamat.',
            ], 422);
        }

        $hasPendingRequest = $license
            ->terminationRequests()
            ->where('termination_status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan penamatan lesen ini sedang '
                    . 'menunggu kelulusan Pengarah.',
            ], 409);
        }

        $hasApprovedRequest = $license
            ->terminationRequests()
            ->where('termination_status', 'approved')
            ->exists();

        if ($hasApprovedRequest) {
            return response()->json([
                'success' => false,
                'message' =>
                'Permohonan penamatan lesen ini '
                    . 'telah diluluskan.',
            ], 409);
        }

        $terminationRequest = DB::transaction(
            function () use (
                $validated,
                $license,
                $userId
            ) {
                $requestedAt = now();

                $securityInvoice = LsankInvoice::query()
                    ->where(
                        'application_id',
                        $license->application_id
                    )
                    ->where(function ($query) {
                        $query
                            ->whereRaw(
                                'LOWER(payment_type) LIKE ?',
                                ['%sekuriti%']
                            )
                            ->orWhereRaw(
                                'LOWER(payment_type) LIKE ?',
                                ['%security%']
                            );
                    })
                    ->lockForUpdate()
                    ->first();

                $terminationRequest =
                    LsankLicenseTerminationRequest::create([
                        'license_id' =>
                        $license->license_id,

                        'application_id' =>
                        $license->application_id,

                        'application_type' =>
                        $validated['application_type']
                            ?? null,

                        'reason' =>
                        trim($validated['reason']),

                        'termination_status' =>
                        'pending',

                        'requested_by_user_id' =>
                        $userId,

                        'requested_at' =>
                        $requestedAt,

                        'security_refund_status' =>
                        'pending',
                    ]);

                if ($securityInvoice) {
                    $currentRefundStatus = strtolower(
                        trim(
                            (string) $securityInvoice
                                ->security_refund_status
                        )
                    );

                    if ($currentRefundStatus !== 'refunded') {
                        $securityInvoice->forceFill([
                            'security_refund_status' =>
                            'pending',

                            'security_refund_requested_at' =>
                            $requestedAt,

                            'security_refunded_at' =>
                            null,

                            'security_refunded_by' =>
                            null,

                            'security_refund_voucher_no' =>
                            null,

                            'security_refund_voucher_date' =>
                            null,

                            'security_refund_amount' =>
                            null,

                            'security_refund_note' =>
                            'Permohonan pemulangan wang sekuriti '
                                . 'sedang diproses melalui permohonan '
                                . 'penamatan lesen.',
                        ])->save();
                    }
                }

                return $terminationRequest;
            }
        );

        $license->unsetRelation('terminationRequest');

        $license->load([
            'application',
            'status',
            'terminationRequest',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
            'Permohonan penamatan berjaya dihantar '
                . 'kepada Pengarah.',

            'data' => [
                'termination_request' =>
                $this->formatTerminationRequest(
                    $terminationRequest
                ),

                'security_refund_status' =>
                'pending',

                'license' =>
                $this->formatLicense($license),
            ],
        ], 201);
    }

    public function approveTermination(
        Request $request,
        LsankLicense $license
    ) {
        if (!$this->isKetuaPengarah($request->user())) {
            return response()->json([
                'success' => false,
                'message' =>
                'Hanya Ketua Pengarah dibenarkan '
                    . 'menutup lesen.',
            ], 403);
        }

        $result = DB::transaction(
            function () use ($request, $license) {
                $lockedLicense = LsankLicense::query()
                    ->where(
                        'license_id',
                        $license->license_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $terminationRequest =
                    LsankLicenseTerminationRequest::query()
                    ->where(
                        'license_id',
                        $lockedLicense->license_id
                    )
                    ->where(
                        'termination_status',
                        'pending'
                    )
                    ->latest('termination_request_id')
                    ->lockForUpdate()
                    ->first();

                if (!$terminationRequest) {
                    return null;
                }

                $directorUserId = (int) (
                    $request->user()->user_id
                    ?? $request->user()->id
                    ?? 0
                );

                $terminationRequest->forceFill([
                    'termination_status' => 'approved',
                    'decided_by_user_id' => $directorUserId,
                    'decided_at' => now(),
                    'director_remark' =>
                    'Permohonan penamatan diluluskan.',
                    'security_refund_status' => 'pending',
                ])->save();

                $securityInvoice = LsankInvoice::query()
                    ->where(
                        'application_id',
                        $lockedLicense->application_id
                    )
                    ->where(function ($query) {
                        $query
                            ->whereRaw(
                                'LOWER(payment_type) LIKE ?',
                                ['%sekuriti%']
                            )
                            ->orWhereRaw(
                                'LOWER(payment_type) LIKE ?',
                                ['%security%']
                            );
                    })
                    ->lockForUpdate()
                    ->first();

                if ($securityInvoice) {
                    $securityInvoiceStatus = strtolower(
                        trim((string) $securityInvoice->status)
                    );

                    if ($securityInvoiceStatus !== 'paid') {
                        throw \Illuminate\Validation\ValidationException
                            ::withMessages([
                                'security_invoice' =>
                                'Wang sekuriti belum dibayar dan '
                                    . 'tidak boleh dipulangkan.',
                            ]);
                    }

                    $currentRefundStatus = strtolower(
                        trim(
                            (string) $securityInvoice
                                ->security_refund_status
                        )
                    );

                    if ($currentRefundStatus !== 'refunded') {
                        $securityInvoice->forceFill([
                            'security_refund_status' =>
                            'pending',

                            'security_refund_requested_at' =>
                            $securityInvoice
                                ->security_refund_requested_at
                                ?? now(),

                            'security_refunded_at' =>
                            null,

                            'security_refunded_by' =>
                            null,

                            'security_refund_note' =>
                            'Pemulangan wang sekuriti sedang diproses '
                                . 'selepas penamatan lesen diluluskan.',
                        ])->save();
                    }
                }

                $lockedLicense->forceFill([
                    'license_status_id' =>
                    $this->terminatedLicenseStatusId(),
                ])->save();

                return $lockedLicense;
            }
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' =>
                'Tiada permohonan penamatan yang sedang '
                    . 'menunggu kelulusan.',
            ], 422);
        }

        $result->unsetRelation('terminationRequest');

        $result->load([
            'application',
            'status',
            'terminationRequest',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
            'Penamatan lesen berjaya diluluskan. '
                . 'Wang sekuriti kini menunggu pemulangan.',
            'license' => $this->formatLicense($result),
        ]);
    }

    /**
     * Generate one license after the application is approved.
     *
     * Safe to call repeatedly. application_id is checked first.
     */
    public function generateForApprovedApplication(
        LsankApplication $application
    ): LsankLicense {
        $application->refresh();

        $applicationStatus = strtolower(
            trim((string) $application->application_status)
        );

        $reviewData = $this->parseJsonMap($application->review_data);

        $workflowStage = strtolower(
            trim((string) (
                $reviewData['workflow_stage']
                ?? $application->workflow_stage
                ?? ''
            ))
        );

        $isApproved =
            in_array($applicationStatus, ['lulus', 'approved'], true) ||
            $workflowStage === 'director_approved';

        if (!$isApproved) {
            throw new \RuntimeException(
                'Lesen hanya boleh dijana selepas permohonan diluluskan.'
            );
        }

        return DB::transaction(function () use (
            $application,
            $reviewData
        ) {
            $existingLicense = LsankLicense::query()
                ->where('application_id', $application->application_id)
                ->lockForUpdate()
                ->first();

            if ($existingLicense) {
                $existingLicense->forceFill([
                    'file_no' =>
                        $application->application_ref_no,

                    'holder_name' =>
                        $this->resolveHolderName($application),

                    'license_type' =>
                        $this->resolveLicenseType($application),

                    'activity_name' =>
                        $this->resolveActivityName($application),

                    'activity_location' =>
                        $this->resolveActivityLocation($application),

                    'latitude' =>
                        $this->resolveLatitude($application),

                    'longitude' =>
                        $this->resolveLongitude($application),
                ])->save();

                $this->ensureArtifacts($existingLicense);
                return $existingLicense->fresh([
                    'application',
                    'status',
                    'terminationRequest',
                ]);
            }
            $activeStatusId = $this->activeLicenseStatusId();

            $licenseStartDate = data_get(
                $reviewData,
                'license.license_start_date',
                data_get(
                    $reviewData,
                    'license_start_date',
                    now()->toDateString()
                )
            );

            $licenseEndDate = data_get(
                $reviewData,
                'license.license_end_date',
                data_get(
                    $reviewData,
                    'license_end_date',
                    now()->addYear()->subDay()->toDateString()
                )
            );

            $licenseNo = $this->nextLicenseNumber($application);
            $qrToken = (string) Str::uuid();
            $verificationUrl = url(
                "/api/licenses/verify/{$qrToken}"
            );

            $license = LsankLicense::create([
                'license_no' => $licenseNo,
                'file_no' => $application->application_ref_no,
                'application_id' => $application->application_id,
                'holder_name' => $this->resolveHolderName($application),
                'license_type' => $this->resolveLicenseType($application),
                'activity_name' => $this->resolveActivityName($application),
                'activity_location' => $this->resolveActivityLocation(
                    $application
                ),
                'latitude' =>
                    $this->resolveLatitude($application),

                'longitude' =>
                    $this->resolveLongitude($application),
                'start_date' => $licenseStartDate,
                'expiry_date' => $licenseEndDate,
                'license_status_id' => $activeStatusId,
                'qr_token' => $qrToken,
                'qr_payload_hash' => hash(
                    'sha256',
                    $verificationUrl
                ),
                'generated_at' => now(),
            ]);

            $this->buildArtifacts($license);

            return $license->fresh([
                'application',
                'status',
                'terminationRequest',
            ]);
        });
    }

    /**
     * Optional API endpoint for manually generating a license
     * from an approved application.
     */
    public function generateFromApplication(
        LsankApplication $application
    ) {
        try {
            $license = $this->generateForApprovedApplication(
                $application
            );

            return response()->json([
                'success' => true,
                'message' => 'Lesen berjaya dijana.',
                'license' => $this->formatLicense($license),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update the editable licence fields from the admin licence screen.
     * The original application is kept unchanged. The generated PDF is
     * invalidated and rebuilt so preview/download never serves stale data.
     */
    public function update(
        Request $request,
        LsankLicense $license
    ) {
        if (!$this->canViewAllLicenses($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan mengemaskini lesen ini.',
            ], 403);
        }

        $validated = $request->validate([
            'license_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('lsank_licenses', 'license_no')
                    ->ignore($license->license_id, 'license_id'),
            ],
            'file_no' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'status' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $statusInput = strtolower(trim($validated['status']));
        $statusAliases = [
            'aktif' => ['aktif', 'active'],
            'tamat' => ['tamat', 'expired'],
            'digantung' => ['digantung', 'suspended'],
            'dibatalkan' => ['dibatalkan', 'cancelled', 'canceled'],
        ];
        $acceptedStatuses = $statusAliases[$statusInput] ?? [$statusInput];

        $status = LsankLicenseStatus::query()
            ->where(function ($query) use ($acceptedStatuses) {
                foreach ($acceptedStatuses as $value) {
                    $query->orWhereRaw('LOWER(status_name) = ?', [$value])
                        ->orWhereRaw('LOWER(status_code) = ?', [$value]);
                }
            })
            ->first();

        if ($status === null) {
            return response()->json([
                'success' => false,
                'message' => 'Status lesen yang dipilih tidak wujud dalam pangkalan data.',
            ], 422);
        }

        $oldPdfPath = $license->license_pdf_path;
        $updatedBy = $request->user()?->user_id
            ?? $request->user()?->id;

        DB::transaction(function () use (
            $license,
            $validated,
            $status,
            $updatedBy
        ): void {
            $license->forceFill([
                'license_no' => trim($validated['license_no']),
                'file_no' => trim($validated['file_no']),
                'start_date' => $validated['start_date'],
                'expiry_date' => $validated['expiry_date'],
                'license_status_id' => $status->license_status_id,
                'license_pdf_path' => null,
                'generated_at' => now(),
            ])->save();

            if (filled($validated['note'] ?? null) && $license->application) {
                $reviewData = is_array($license->application->review_data)
                    ? $license->application->review_data
                    : [];
                $history = data_get($reviewData, 'license_updates', []);
                $history = is_array($history) ? $history : [];
                $history[] = [
                    'note' => trim($validated['note']),
                    'updated_at' => now()->toIso8601String(),
                    'updated_by' => $updatedBy,
                ];
                data_set($reviewData, 'license_updates', $history);
                $license->application->forceFill([
                    'review_data' => $reviewData,
                ])->save();
            }
        });

        if (filled($oldPdfPath)) {
            Storage::disk('local')->delete($oldPdfPath);
        }

        try {
            $license->refresh();
            $this->buildArtifacts($license);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Maklumat lesen disimpan, tetapi PDF gagal dijana semula: '
                    . $e->getMessage(),
            ], 500);
        }

        $license->refresh()->load([
            'application',
            'status',
            'terminationRequest',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maklumat lesen berjaya dikemaskini.',
            'license' => $this->formatLicense($license),
        ]);
    }

    /**
     * Download the licence PDF.
     *
     * Normal users may download their own licence. Admin/staff roles may
     * download any licence. Downloads are not limited to one attempt.
     */
    public function downloadPdf(
        Request $request,
        LsankLicense $license
    ) {
        if (!$this->canAccessLicense($request->user(), $license)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan memuat turun lesen ini.',
            ], 403);
        }

        try {
            $this->ensureArtifacts($license);
            $license->refresh();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'PDF lesen gagal dijana: ' . $e->getMessage(),
            ], 500);
        }

        if (
            empty($license->license_pdf_path) ||
            !Storage::disk('local')->exists(
                $license->license_pdf_path
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Fail PDF lesen tidak dijumpai.',
            ], 404);
        }

        $license->forceFill([
            'pdf_downloaded_at' => now(),
        ])->save();

        $pdfContent = Storage::disk('local')->get(
            $license->license_pdf_path
        );

        return response()->streamDownload(
            static function () use ($pdfContent): void {
                echo $pdfContent;
            },
            "{$license->license_no}.pdf",
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    /**
     * Open the licence PDF inline for viewing or printing.
     *
     * Opening the PDF is not limited to one attempt.
     */
    public function printPdf(
        Request $request,
        LsankLicense $license
    ) {
        if (!$this->canAccessLicense($request->user(), $license)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan melihat PDF lesen ini.',
            ], 403);
        }

        try {
            $this->ensureArtifacts($license);
            $license->refresh();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'PDF lesen gagal dijana: ' . $e->getMessage(),
            ], 500);
        }

        if (
            empty($license->license_pdf_path) ||
            !Storage::disk('local')->exists(
                $license->license_pdf_path
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Fail PDF lesen tidak dijumpai.',
            ], 404);
        }

        $license->forceFill([
            'printed_at' => now(),
        ])->save();

        return response(
            Storage::disk('local')->get(
                $license->license_pdf_path
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                'inline; filename="'
                    . $license->license_no
                    . '.pdf"',
            ]
        );
    }

    /**
     * Download QR PNG once.
     */
    public function downloadQr(
        Request $request,
        LsankLicense $license
    ) {
        if (!$this->canAccessLicense($request->user(), $license)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dibenarkan memuat turun kod QR ini.',
            ], 403);
        }

        if ($license->qr_downloaded_at !== null) {
            return response()->json([
                'success' => false,
                'message' =>
                'Kod QR hanya boleh dimuat turun sekali.',
            ], 409);
        }

        $this->ensureArtifacts($license);

        if (
            empty($license->qr_code_path) ||
            !Storage::disk('local')->exists(
                $license->qr_code_path
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Fail SVG kod QR tidak dijumpai.',
            ], 404);
        }

        $license->forceFill([
            'qr_downloaded_at' => now(),
        ])->save();

        $qrContent = Storage::disk('local')->get(
            $license->qr_code_path
        );

        return response()->streamDownload(
            static function () use ($qrContent): void {
                echo $qrContent;
            },
            "{$license->license_no}-QR.svg",
            [
                'Content-Type' => 'image/svg+xml',
            ]
        );
    }

    /**
     * Public QR verification endpoint.
     */
    public function verify(string $token)
    {
        $license = LsankLicense::query()
            ->with([
                'application',
                'status',
            ])
            ->where('qr_token', $token)
            ->first();

        if (!$license) {
            return response()->json([
                'valid' => false,
                'message' => 'Kod QR lesen tidak sah.',
            ], 404);
        }

        $expired = $license->expiry_date !== null &&
            now()->startOfDay()->gt(
                $license->expiry_date->copy()->startOfDay()
            );

        $statusCode = strtolower(
            trim((string) (
                $license->status?->status_code
                ?? $license->status?->status_name
                ?? ''
            ))
        );

        $active = !$expired &&
            (
                $statusCode === '' ||
                str_contains($statusCode, 'aktif') ||
                str_contains($statusCode, 'active')
            );

        return response()->json([
            'valid' => $active,
            'license_no' => $license->license_no,
            'file_no' => $license->file_no,
            'holder_name' => $license->holder_name,
            'holder_address' =>
            $this->resolveHolderAddress($license->application),
            'registration_no' =>
            $this->resolveRegistrationNo($license->application),
            'business_phone' =>
            $this->resolveBusinessPhone($license->application),
            'license_type' => $license->license_type,
            'activity_name' => $license->activity_name,
'activity_location' =>
    (
        $license->activity_location !== null &&
        trim((string) $license->activity_location) !== '' &&
        trim((string) $license->activity_location) !== '-'
    )
        ? $license->activity_location
        : $license->application?->activity_location,
'latitude' =>
    $license->latitude !== null
        ? $license->latitude
        : $license->application?->latitude,
'longitude' =>
    $license->longitude !== null
        ? $license->longitude
        : $license->application?->longitude,
            'start_date' => optional(
                $license->start_date
            )?->format('Y-m-d'),
            'expiry_date' => optional(
                $license->expiry_date
            )?->format('Y-m-d'),
            'status' => $expired
                ? 'expired'
                : (
                    $license->status?->status_name
                    ?? 'Aktif'
                ),
        ]);
    }

    private function ensureArtifacts(
        LsankLicense $license
    ): void {
        $missingPdf =
            empty($license->license_pdf_path) ||
            !Storage::disk('local')->exists(
                $license->license_pdf_path
            );

        $missingQr =
            empty($license->qr_code_path) ||
            !Storage::disk('local')->exists(
                $license->qr_code_path
            );

        if ($missingPdf || $missingQr) {
            $this->buildArtifacts($license);
        }
    }

    private function buildArtifacts(
        LsankLicense $license
    ): void {
        $license->loadMissing([
            'application',
            'status',
        ]);

        $verificationUrl = url(
            "/api/licenses/verify/{$license->qr_token}"
        );

        $safeLicenseNo = str_replace(
            ['/', '\\', ' '],
            '-',
            $license->license_no
        );

        $qrRelativePath =
            "licenses/qr/{$safeLicenseNo}.svg";

        $pdfRelativePath =
            "licenses/pdf/{$safeLicenseNo}.pdf";

        Storage::disk('local')->makeDirectory(
            'licenses/qr'
        );

        Storage::disk('local')->makeDirectory(
            'licenses/pdf'
        );

        $qrSvg = QrCode::format('svg')
            ->size(420)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($verificationUrl);

        Storage::disk('local')->put(
            $qrRelativePath,
            $qrSvg
        );

        $qrDataUri =
            'data:image/svg+xml;base64,'
            . base64_encode($qrSvg);

        $latestReceipt = DB::table('lsank_receipts as receipts')
            ->join(
                'lsank_invoices as invoices',
                'invoices.invoice_id',
                '=',
                'receipts.invoice_id'
            )
            ->where('invoices.application_id', $license->application_id)
            ->where('receipts.status', 'valid')
            ->orderByDesc('receipts.receipt_date')
            ->orderByDesc('receipts.receipt_id')
            ->select([
                'receipts.receipt_id',
                'receipts.receipt_no',
                'receipts.receipt_date',
                'receipts.amount',
                'receipts.payment_id',
                'invoices.invoice_id',
                'invoices.invoice_no',
                'invoices.payment_type',
            ])
            ->first();

        $pdf = Pdf::loadView(
            'licenses.certificate',
            [
                'license' => $license,
                'application' => $license->application,
                'qrDataUri' => $qrDataUri,
                'verificationUrl' => $verificationUrl,
                'receipt' => $latestReceipt,
                'receiptNo' => $latestReceipt?->receipt_no,
            ]
        )->setPaper('a4', 'portrait');

        Storage::disk('local')->put(
            $pdfRelativePath,
            $pdf->output()
        );

        $license->forceFill([
            'qr_code_path' => $qrRelativePath,
            'license_pdf_path' => $pdfRelativePath,
        ])->save();
    }
    private function nextLicenseNumber(
        LsankApplication $application
    ): string {
        $year = now()->format('Y');

        $typeName = strtolower(
            trim((string) (
                $application->license_type
                ?? $application->activity_type
                ?? $application->application_type
                ?? ''
            ))
        );
        $prefix = str_contains($typeName, 'efluen')
            ? 'EF'
            : 'WB';

        $lastLicense = LsankLicense::query()
            ->whereYear('created_at', $year)
            ->where('license_no', 'like', "{$prefix}-{$year}-%")
            ->lockForUpdate()
            ->latest('license_id')
            ->first();

        $lastRunningNumber = 0;

        if ($lastLicense) {
            $segments = explode(
                '-',
                $lastLicense->license_no
            );

            $lastRunningNumber = (int) end($segments);
        }

        return sprintf(
            '%s-%s-%04d',
            $prefix,
            $year,
            $lastRunningNumber + 1
        );
    }

    private function activeLicenseStatusId(): ?int
    {
        if (!class_exists(LsankLicenseStatus::class)) {
            return null;
        }

        $status = LsankLicenseStatus::query()
            ->whereIn('status_code', [
                'active',
                'aktif',
            ])
            ->first();

        if (!$status) {
            $status = LsankLicenseStatus::query()->create([
                'status_code' => 'active',
                'status_name' => 'Aktif',
            ]);
        }

        return (int) $status->license_status_id;
    }

    private function resolveHolderName(
        LsankApplication $application
    ): string {
        return trim((string) (
            $application->business_name
            ?? $application->applicant_name
            ?? $application->user?->name
            ?? '-'
        ));
    }

    private function resolveLicenseType(
        LsankApplication $application
    ): string {
        return trim((string) (
            $application->license_type
            ?? (
                $application->application_type === 'effluent'
                ? 'Aktiviti Pelepasan Efluen'
                : 'Aktiviti Badan Perairan'
            )
        ));
    }

    private function resolveActivityName(
        LsankApplication $application
    ): string {
        return trim((string) (
            $application->activity_name
            ?? $application->activity_type
            ?? $application->activity_details
            ?? '-'
        ));
    }

    private function resolveActivityLocation(
        LsankApplication $application
    ): string {
        return trim((string) (
            $application->activity_location
            ?? $application->location
            ?? $application->district
            ?? '-'
        ));
    }

    private function resolveLatitude(
        LsankApplication $application
    ): ?float {
        $value = $application->latitude;
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === null || $value === '') {
            return null;
        }

        $latitude = (float) $value;
        $latitude = (float) $value;

        if ($latitude < -90 || $latitude > 90) {
            return null;
        }
        if ($latitude < -90 || $latitude > 90) {
            return null;
        }

        return $latitude;
    }
   

    private function resolveLongitude(
        LsankApplication $application
    ): ?float {
        $value = $application->longitude;

        if ($value === null || $value === '') {
            return null;
        }
        if ($value === null || $value === '') {
            return null;
        }

        $longitude = (float) $value;
        $longitude = (float) $value;

        if ($longitude < -180 || $longitude > 180) {
            return null;
        }
        if ($longitude < -180 || $longitude > 180) {
            return null;
        }

        return $longitude;
    }

    private function resolveHolderAddress(
        ?LsankApplication $application
    ): ?string {
        if ($application === null) {
            return null;
        }

        $value = trim((string) (
            data_get($application, 'business_address')
            ?? data_get($application, 'applicant_address')
            ?? data_get($application, 'address')
            ?? ''
        ));

        return $value !== '' && $value !== '-'
            ? $value
            : null;
    }

    private function resolveRegistrationNo(
        ?LsankApplication $application
    ): ?string {
        if ($application === null) {
            return null;
        }

        $value = trim((string) (
            data_get($application, 'registration_no')
            ?? data_get($application, 'company_registration_no')
            ?? data_get($application, 'business_registration_no')
            ?? data_get($application, 'identity_no')
            ?? data_get($application, 'identification_no')
            ?? data_get($application, 'ic_no')
            ?? ''
        ));

        return $value !== '' && $value !== '-'
            ? $value
            : null;
    }

    private function resolveBusinessPhone(
        ?LsankApplication $application
    ): ?string {
        if ($application === null) {
            return null;
        }

        $value = trim((string) (
            data_get($application, 'business_phone')
            ?? data_get($application, 'applicant_phone')
            ?? data_get($application, 'phone')
            ?? data_get($application, 'mobile_no')
            ?? data_get($application, 'contact_no')
            ?? ''
        ));

        return $value !== '' && $value !== '-'
            ? $value
            : null;
    }

    private function resolveBusinessEmail(
        ?LsankApplication $application
    ): ?string {
        if ($application === null) {
            return null;
        }

        $value = trim((string) (
            data_get($application, 'business_email')
            ?? data_get($application, 'applicant_email')
            ?? data_get($application, 'email')
            ?? ''
        ));

        return $value !== '' && $value !== '-'
            ? $value
            : null;
    }

    private function parseJsonMap(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? $decoded
            : [];
    }

    private function formatTerminationRequest(
        ?LsankLicenseTerminationRequest $termination
    ): ?array {
        if ($termination === null) {
            return null;
        }

        return [
            'termination_request_id' =>
            $termination->termination_request_id,

            'license_id' =>
            $termination->license_id,

            'application_id' =>
            $termination->application_id,

            'application_type' =>
            $termination->application_type,

            'status' =>
            $termination->termination_status,

            'reason' =>
            $termination->reason,

            'requested_at' => optional(
                $termination->requested_at
            )?->toIso8601String(),

            'decided_by_user_id' =>
            $termination->decided_by_user_id,

            'director_remark' =>
            $termination->director_remark,

            'rejection_reason' =>
            $termination->termination_status === 'rejected'
                ? $termination->director_remark
                : null,

            'decided_at' => optional(
                $termination->decided_at
            )?->toIso8601String(),

            'security_refund_status' =>
            $termination->security_refund_status,

            'security_refund_amount' =>
            $termination->security_refund_amount,

            'security_refund_reference' =>
            $termination->security_refund_reference,

            'security_refund_note' =>
            $termination->security_refund_note,

            'security_refunded_at' => optional(
                $termination->security_refunded_at
            )?->toIso8601String(),
        ];
    }

    private function isKetuaPengarah(
        mixed $user
    ): bool {
        $roleCandidates = [
            data_get($user, 'role.role_name'),
            data_get($user, 'role.name'),
            data_get($user, 'role.role_code'),
            data_get($user, 'role.code'),
            data_get($user, 'role_name'),
            data_get($user, 'role_code'),
            data_get($user, 'user_role'),
            data_get($user, 'user_type'),
            data_get($user, 'role'),
        ];

        foreach ($roleCandidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $normalized = strtolower(
                trim((string) $candidate)
            );

            $normalized = str_replace(
                ['_', '-'],
                ' ',
                $normalized
            );

            $normalized = preg_replace(
                '/\s+/',
                ' ',
                $normalized
            );

            if (
                in_array(
                    $normalized,
                    [
                        'ketua pengarah',
                        'director general',
                    ],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function terminatedLicenseStatusId(): int
    {
        $status = LsankLicenseStatus::query()
            ->where(function ($query) {
                $query
                    ->whereIn('status_code', [
                        'terminated',
                        'tamat',
                        'closed',
                    ])
                    ->orWhereIn('status_name', [
                        'Tamat',
                        'Ditamatkan',
                    ]);
            })
            ->first();

        if (!$status) {
            $status = LsankLicenseStatus::query()
                ->create([
                    'status_code' => 'terminated',
                    'status_name' => 'Tamat',
                ]);
        }

        return (int) $status->license_status_id;
    }

   private function noticeRestrictionForLicense($license): array
{
    $applicationId = (int) (
        $license->application_id ?? 0
    );

    if ($applicationId <= 0) {
        return [
            'active' => false,
            'count' => 0,
            'message' => null,
            'notices' => [],
        ];
    }

    $userId = (int) DB::table('lsank_applications')
        ->where('application_id', $applicationId)
        ->value('user_id');

    if ($userId <= 0) {
        return [
            'active' => false,
            'count' => 0,
            'message' => null,
            'notices' => [],
        ];
    }

    $service = app(
        NoticeRestrictionService::class
    );

    // Important for notices created before
    // restriction module was introduced.
    $service->syncExistingNoticesForUser(
        $userId
    );

    return $service->summaryForUser(
        $userId
    );
}

    private function firstFilledValue(array $values, mixed $fallback = null): mixed
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '' || $value === '-' || strtolower($value) === 'null') {
                    continue;
                }
            }

            return $value;
        }

        return $fallback;
    }

    private function formatLicense(
        LsankLicense $license
    ): array {
        $license->loadMissing([
            'application.type',
            'application.applicantType',
            'application.districtMaster',
            'application.category.applicationType',
            'application.waterBody.activityType',
            'application.effluent.serviceType',
            'status',
            'terminationRequest',
        ]);

        $application = $license->application;

        $applicationTypeCode = strtoupper(trim((string) (
            data_get($application, 'category.applicationType.type_code')
            ?? data_get($application, 'type.type_code')
            ?? data_get($application, 'application_type')
            ?? ''
        )));

        $applicationType = match (true) {
            str_contains($applicationTypeCode, 'EFFLUENT'),
            str_contains($applicationTypeCode, 'EFLUEN'),
            str_contains($applicationTypeCode, 'PELEPASAN') => 'effluent',
            default => 'water',
        };

        $moduleRecord = $applicationType === 'effluent'
            ? $application?->effluent
            : $application?->waterBody;

        $activityName = $license->activity_name
            ?? data_get($application, 'category.category_name')
            ?? data_get($moduleRecord, 'serviceType.service_name')
            ?? data_get($moduleRecord, 'activityType.activity_name')
            ?? data_get($application, 'activity_name')
            ?? data_get($application, 'activity_type');

        $activityLocation = $this->firstFilledValue([
            $license->activity_location,
            data_get($moduleRecord, 'activity_location'),
            data_get($application, 'activity_location'),
            data_get($application, 'business_address'),
        ]);

        $latitude = $this->firstFilledValue([
            $license->latitude,
            data_get($moduleRecord, 'latitude'),
            data_get($application, 'latitude'),
        ]);

        $longitude = $this->firstFilledValue([
            $license->longitude,
            data_get($moduleRecord, 'longitude'),
            data_get($application, 'longitude'),
        ]);
        $holderAddress =
            $this->resolveHolderAddress($application);

        $registrationNo =
            $this->resolveRegistrationNo($application);

        $businessPhone =
            $this->resolveBusinessPhone($application);

        $businessEmail =
            $this->resolveBusinessEmail($application);

        $securityInvoice = LsankInvoice::query()
            ->where(
                'application_id',
                $license->application_id
            )
            ->where(function ($query) {
                $query
                    ->whereRaw(
                        'LOWER(payment_type) LIKE ?',
                        ['%sekuriti%']
                    )
                    ->orWhereRaw(
                        'LOWER(payment_type) LIKE ?',
                        ['%security%']
                    );
            })
            ->latest('invoice_id')
            ->first();

        // A licence only displays completed payment evidence. Read from
        // receipts and join the related invoice solely for payment metadata.
        $receiptItems = DB::table('lsank_receipts as receipts')
            ->join(
                'lsank_invoices as invoices',
                'invoices.invoice_id',
                '=',
                'receipts.invoice_id'
            )
            ->where('invoices.application_id', $license->application_id)
            ->where('receipts.status', 'valid')
            ->orderBy('receipts.receipt_date')
            ->orderBy('receipts.receipt_id')
            ->get([
                'receipts.receipt_id',
                'receipts.receipt_no',
                'receipts.invoice_id',
                'receipts.payment_id',
                'receipts.receipt_date',
                'receipts.amount',
                'receipts.receipt_pdf_path',
                'receipts.status as receipt_status',
                'receipts.created_at',
                'invoices.invoice_no',
                'invoices.payment_type',
            ])
            ->map(static fn (object $receipt): array => [
                'receipt_id' => $receipt->receipt_id,
                'receipt_no' => $receipt->receipt_no,
                'receipt_date' => $receipt->receipt_date,
                'receipt_status' => $receipt->receipt_status,
                'amount' => (float) $receipt->amount,
                'receipt_pdf_path' => $receipt->receipt_pdf_path,
                'invoice_id' => $receipt->invoice_id,
                'invoice_no' => $receipt->invoice_no,
                'payment_id' => $receipt->payment_id,
                'payment_type' => $receipt->payment_type,
                'payment_status' => 'paid',
                'paid' => true,
                'paid_at' => $receipt->receipt_date
                    ?? $receipt->created_at,
            ])
            ->values();

        return [
            'license_id' => $license->license_id,
            'application_id' => $license->application_id,
            'application_type' => $applicationType,
            'license_no' => $license->license_no,
            'file_no' => $license->file_no,

            'holder_name' =>
            $license->holder_name,

            'company_name' =>
            data_get($application, 'company_name')
                ?? data_get($application, 'business_name')
                ?? $license->holder_name
                ?? '-',

            'applicant_name' =>
            data_get($application, 'applicant_name')
                ?? '-',

            'holder_address' =>
            $holderAddress,

            'registration_no' =>
            $registrationNo,

            'business_phone' =>
            $businessPhone,

            'business_email' =>
            $businessEmail,

            'license_type' =>
            $license->license_type,

            'activity_name' => $activityName,
            'activity_location' => $activityLocation,
            'latitude' => $latitude,
            'longitude' => $longitude,

            'application' => $application
                ? [
                    'application_id' =>
                    $application->application_id,

                    'application_ref_no' =>
                    $application->application_ref_no,

                    'application_type' => $applicationType,

                    'application_type_id' =>
                    $application->application_type_id,

                    'applicant_type_id' =>
                    $application->applicant_type_id,

                    'district_id' =>
                    $application->district_id,

                    'category_id' =>
                    $application->category_id,

                    'is_one_off' =>
                    (bool) $application->is_one_off,

                    'file_running_number' =>
                    $application->file_running_number,

                    'applicant_type' =>
                    data_get($application, 'applicantType.type_name')
                        ?? $application->applicant_type,

                    'applicant_type_master' =>
                    $application->applicantType?->toArray(),

                    'district' =>
                    data_get($application, 'districtMaster.district_name')
                        ?? $application->getRawOriginal('district'),

                    'district_master' =>
                    $application->districtMaster?->toArray(),

                    'category' =>
                    $application->category?->toArray(),

                    'applicant_name' =>
                    data_get($application, 'applicant_name'),

                    'business_name' =>
                    data_get($application, 'business_name'),

                    'company_name' =>
                    data_get($application, 'company_name'),

                    'registration_no' =>
                    $registrationNo,

                    'company_registration_no' =>
                    data_get(
                        $application,
                        'company_registration_no'
                    ),

                    'business_address' =>
                    data_get($application, 'business_address'),

                    'applicant_address' =>
                    data_get($application, 'applicant_address'),

                    'address' =>
                    data_get($application, 'address'),

                    'business_phone' =>
                    $businessPhone,

                    'applicant_phone' =>
                    data_get($application, 'applicant_phone'),

                    'phone' =>
                    data_get($application, 'phone'),

                    'business_email' =>
                    $businessEmail,

                    'applicant_email' =>
                    data_get($application, 'applicant_email'),

                    'email' =>
                    data_get($application, 'email'),

                    'activity_name' =>
                    data_get($application, 'activity_name'),

                    'activity_type' =>
                    data_get($application, 'activity_type'),

                    'activity_location' => $activityLocation,

                    'latitude' => $latitude,

                    'longitude' => $longitude,

                    'water_body' =>
                    $application->waterBody?->toArray(),

                    'effluent' =>
                    $application->effluent?->toArray(),

                    'review_data' =>
                    $application->review_data,

                    'submitted_data' =>
                    $application->submitted_data,
                ]
                : null,

            'water_body' =>
            $application?->waterBody?->toArray(),

            'effluent' =>
            $application?->effluent?->toArray(),

            'receipt_items' => $receiptItems,
            'receipts' => $receiptItems,

            'start_date' => optional(
                $license->start_date
            )?->format('Y-m-d'),
            'expiry_date' => optional(
                $license->expiry_date
            )?->format('Y-m-d'),
            'license_status_id' => $license->license_status_id,
            'status' => $license->status?->status_name
                ?? 'Aktif',
            'generated_at' => optional(
                $license->generated_at
            )?->toIso8601String(),

            'security_refund_status' =>
            $securityInvoice?->security_refund_status
                ?? $license->terminationRequest
                ?->security_refund_status
                ?? 'not_requested',

            'security_refund_requested_at' => optional(
                $securityInvoice?->security_refund_requested_at
            )?->toIso8601String(),

            'security_refunded_at' => optional(
                $securityInvoice?->security_refunded_at
            )?->toIso8601String(),

            'security_refund_amount' =>
            $securityInvoice?->security_refund_amount,

            'security_refund_note' =>
            $securityInvoice?->security_refund_note,

            'termination_request' =>
            $this->formatTerminationRequest(
                $license->terminationRequest
            ),

            'can_download_pdf' => true,

            'can_print' => true,

            'can_download_qr' =>
            $license->qr_downloaded_at === null,

            'pdf_downloaded_at' => optional(
                $license->pdf_downloaded_at
            )?->toIso8601String(),

            'printed_at' => optional(
                $license->printed_at
            )?->toIso8601String(),

            'qr_downloaded_at' => optional(
                $license->qr_downloaded_at
            )?->toIso8601String(),

            'download_pdf_url' => route(
                'licenses.download-pdf',
                $license
            ),

            'print_pdf_url' => route(
                'licenses.print-pdf',
                $license
            ),

            'download_qr_url' => route(
                'licenses.download-qr',
                $license
            ),

            'verify_url' => url(
                "/api/licenses/verify/{$license->qr_token}"
            ),
            'notice_restriction' =>
    $this->noticeRestrictionForLicense(
        $license
    ),
        ];

        return response()->file($absolutePath, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' =>
        'inline; filename="' . $fileName . '"',
    'Cache-Control' =>
        'no-store, no-cache, must-revalidate, max-age=0',
    'Pragma' => 'no-cache',
    'Expires' => '0',
]);


return response()->download(
    $absolutePath,
    $fileName,
    [
        'Content-Type' => 'application/pdf',
        'Cache-Control' =>
            'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]
);
    }
}


