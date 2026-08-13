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
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use App\Models\LsankLicenseTerminationRequest;
use App\Models\LsankInvoice;

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
    public function show(LsankLicense $license)
    {
        $license->load([
            'application',
            'status',
            'terminationRequest'
        ]);

        return response()->json([
            'success' => true,
            'license' => $this->formatLicense($license),
        ]);
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
            'application',
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

    public function adminIndex(Request $request)
    {
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
            fn(LsankLicense $license) => $this->formatLicense($license)
        );

        return response()->json([
            'success' => true,
            'licenses' => $licenses,
        ]);
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
                'latitude' => $application->latitude,
                'longitude' => $application->longitude,
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
     * Download PDF once.
     */
    public function downloadPdf(
        Request $request,
        LsankLicense $license
    ) {
        if ($license->pdf_downloaded_at !== null) {
            return response()->json([
                'success' => false,
                'message' =>
                'Fail lesen hanya boleh dimuat turun sekali.',
            ], 409);
        }

        $this->ensureArtifacts($license);

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
     * Open PDF inline once for printing.
     *
     * Note: the backend can record one print-open event, but the browser
     * cannot guarantee the user physically printed the document.
     */
    public function printPdf(
        Request $request,
        LsankLicense $license
    ) {
        if ($license->printed_at !== null) {
            return response()->json([
                'success' => false,
                'message' =>
                'Lesen hanya boleh dibuka untuk cetakan sekali.',
            ], 409);
        }

        $this->ensureArtifacts($license);

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
            'license_type' => $license->license_type,
            'activity_name' => $license->activity_name,
            'activity_location' => $license->activity_location,
            'latitude' => $license->latitude,
            'longitude' => $license->longitude,
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

        $pdf = Pdf::loadView(
            'licenses.certificate',
            [
                'license' => $license,
                'application' => $license->application,
                'qrDataUri' => $qrDataUri,
                'verificationUrl' => $verificationUrl,
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

        $latitude = (float) $value;

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

        $longitude = (float) $value;

        if ($longitude < -180 || $longitude > 180) {
            return null;
        }

        return $longitude;
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

    private function formatLicense(
        LsankLicense $license
    ): array {
        $license->loadMissing([
            'application',
            'status',
            'terminationRequest',
        ]);

        $application = $license->application;

        $applicationType = strtolower(
            trim((string) ($application?->application_type ?? ''))
        );

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

        return [
            'license_id' => $license->license_id,
            'application_id' => $license->application_id,
            'application_type' => $applicationType,

            'application' => $application ? [
                'application_id' => $application->application_id,
                'application_type' => $applicationType,
                'application_ref_no' => $application->application_ref_no,
                'business_name' => $application->business_name,
                'applicant_name' => $application->applicant_name,
                'activity_name' => $application->activity_name,
                'activity_location' => $application->activity_location,
            ] : null,

            'license_no' => $license->license_no,
            'file_no' => $license->file_no,
            'holder_name' => $license->holder_name,
            'license_type' => $license->license_type,
            'activity_name' => $license->activity_name,
            'activity_location' => $license->activity_location,

            'latitude' => $license->latitude,
            'longitude' => $license->longitude,

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

            'can_download_pdf' =>
            $license->pdf_downloaded_at === null,

            'can_print' =>
            $license->printed_at === null,

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
        ];
    }
}
