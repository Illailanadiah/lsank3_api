<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankCriminalCase;
use App\Models\LsankLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CriminalCaseController extends Controller
{
    public function index(Request $request)
    {
        $query = LsankCriminalCase::query()
            ->with([
                'license:license_id,application_id,license_no,file_no,holder_name,license_type,activity_name',
            ])
            ->latest('criminal_case_id');

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'case_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'efiling_case_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'file_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'license_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'party_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'offence',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'section_regulation',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'court_location',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'judge_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'case_status',
                trim(
                    (string) $request->input('status')
                )
            );
        }

        if ($request->filled('punishment')) {
            $query->where(
                'punishment',
                trim(
                    (string) $request->input('punishment')
                )
            );
        }

        $cases = $query
            ->get()
            ->map(
                fn (LsankCriminalCase $case) =>
                    $this->formatCase($case)
            )
            ->values();

        return response()->json([
            'success' => true,
            'cases' => $cases,
        ]);
    }

    public function nextNumber()
    {
        return response()->json([
            'success' => true,
            'case_no' =>
                $this->peekNextCaseNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated =
            $this->validateRequest($request);

        $validated =
            $this->prepareLicenseData(
                $validated
            );

        $case = DB::transaction(
            function () use (
                $request,
                $validated
            ) {
                $userId =
                    $this->currentUserId($request);

                return LsankCriminalCase::create([
                    ...$validated,
                    'case_no' =>
                        $this->nextCaseNumber(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        );

        $case = LsankCriminalCase::query()
            ->with('license')
            ->findOrFail(
                $case->criminal_case_id
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Kes jenayah berjaya disimpan.',
            'case' =>
                $this->formatCase($case),
        ], 201);
    }

    public function show($case)
    {
        $record =
            $this->findCase($case);

        return response()->json([
            'success' => true,
            'case' =>
                $this->formatCase($record),
        ]);
    }

    public function update(
        Request $request,
        $case
    ) {
        $record =
            $this->findCase($case);

        $validated =
            $this->validateRequest($request);

        $validated =
            $this->prepareLicenseData(
                $validated
            );

        DB::transaction(
            function () use (
                $request,
                $record,
                $validated
            ) {
                $record->forceFill([
                    ...$validated,
                    'updated_by' =>
                        $this->currentUserId(
                            $request
                        ),
                ]);

                $record->saveOrFail();
            }
        );

        $record = LsankCriminalCase::query()
            ->with('license')
            ->findOrFail(
                $record->criminal_case_id
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Kes jenayah berjaya dikemas kini.',
            'case' =>
                $this->formatCase($record),
        ]);
    }

    public function destroy($case)
    {
        $record =
            $this->findCase($case);

        $caseNo = $record->case_no;

        $record->delete();

        return response()->json([
            'success' => true,
            'message' =>
                "Kes jenayah {$caseNo} berjaya dipadam.",
        ]);
    }

    private function validateRequest(
        Request $request
    ): array {
        return $request->validate([
            'legal_referral_id' => [
                'nullable',
                'integer',
            ],

            'notice_id' => [
                'nullable',
                'integer',
            ],

            'user_id' => [
                'nullable',
                'integer',
            ],

            'application_id' => [
                'nullable',
                'integer',
            ],

            /*
             * Effluent + Water Body:
             * A real licence holder MUST be selected.
             *
             * Pengabstrakan Air:
             * licence information is entered manually,
             * therefore license_id may be null.
             */
            'license_id' => [
                'nullable',
                'integer',
                'exists:lsank_licenses,license_id',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use ($request) {
                    $offence = trim(
                        (string) $request->input(
                            'offence',
                            ''
                        )
                    );

                    $requiresLinkedLicense =
                        in_array(
                            $offence,
                            [
                                'Aktiviti Pelepasan Efluen',
                                'Aktiviti Badan Perairan',
                            ],
                            true
                        );

                    if (
                        $requiresLinkedLicense &&
                        empty($value)
                    ) {
                        $fail(
                            'Sila pilih pemegang lesen untuk kesalahan ini.'
                        );
                    }
                },
            ],

            'file_no' => [
                'required',
                'string',
                'max:100',
            ],

            'license_no' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * Required for ALL criminal cases.
             * This value is now saved directly from $validated.
             */
            'efiling_case_no' => [
                'required',
                'string',
                'max:255',
            ],

            'offence' => [
                'required',
                'string',
                'in:Aktiviti Pelepasan Efluen,Aktiviti Badan Perairan,Pengabstrakan Air',
            ],

            'party_name' => [
                'required',
                'string',
                'max:255',
            ],

            'section_regulation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'court_location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'judge_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mention_date' => [
                'nullable',
                'date',
            ],

            'punishment' => [
                'required',
                'string',
                'in:Denda,Penjara,Tiada',
            ],

            'outstanding_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'case_status' => [
                'required',
                'string',
                'in:Sedang Berjalan,Selesai',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);
    }

    /**
     * Effluent / Water Body:
     * Never trust holder / file / licence details typed by
     * the frontend. Resolve them from the selected licence.
     *
     * Pengabstrakan Air:
     * license_id is null, therefore the manually entered
     * party_name, file_no and license_no remain unchanged.
     */
    private function prepareLicenseData(
        array $validated
    ): array {
        $licenseId = (int) (
            $validated['license_id'] ?? 0
        );

        if ($licenseId <= 0) {
            $validated['license_id'] = null;
            $validated['application_id'] =
                $validated['application_id']
                ?? null;
            $validated['user_id'] =
                $validated['user_id']
                ?? null;

            return $validated;
        }

        $license = LsankLicense::query()
            ->where(
                'license_id',
                $licenseId
            )
            ->firstOrFail();

        $application = null;

        if (
            method_exists(
                $license,
                'application'
            )
        ) {
            $application =
                $license
                    ->application()
                    ->first();
        }

        $validated['license_id'] =
            $license->license_id;

        $validated['application_id'] =
            $license->application_id
            ?? $application?->application_id
            ?? $validated['application_id']
            ?? null;

        $validated['user_id'] =
            $application?->user_id
            ?? $validated['user_id']
            ?? null;

        $validated['party_name'] =
            trim(
                (string) (
                    $license->holder_name
                    ?? $validated['party_name']
                )
            );

        $validated['file_no'] =
            trim(
                (string) (
                    $license->file_no
                    ?? $application?->file_no
                    ?? $validated['file_no']
                )
            );

        $validated['license_no'] =
            trim(
                (string) (
                    $license->license_no
                    ?? $validated['license_no']
                )
            );

        return $validated;
    }

    private function findCase(
        $case
    ): LsankCriminalCase {
        return LsankCriminalCase::query()
            ->with('license')
            ->where(
                'criminal_case_id',
                (int) $case
            )
            ->firstOrFail();
    }

    private function currentUserId(
        Request $request
    ): ?int {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $userId = (int) (
            $user->user_id
            ?? $user->id
            ?? 0
        );

        return $userId > 0
            ? $userId
            : null;
    }

    private function nextCaseNumber(): string
    {
        $year =
            now()->format('Y');

        $last =
            LsankCriminalCase::query()
                ->where(
                    'case_no',
                    'like',
                    "LSANK-3/2/{$year}-%"
                )
                ->lockForUpdate()
                ->latest(
                    'criminal_case_id'
                )
                ->first();

        $running = 0;

        if ($last) {
            $parts =
                explode(
                    '-',
                    $last->case_no
                );

            $running =
                (int) end($parts);
        }

        return sprintf(
            'LSANK-3/2/%s-%04d',
            $year,
            $running + 1
        );
    }

    private function peekNextCaseNumber(): string
    {
        $year =
            now()->format('Y');

        $last =
            LsankCriminalCase::query()
                ->where(
                    'case_no',
                    'like',
                    "LSANK-3/2/{$year}-%"
                )
                ->latest(
                    'criminal_case_id'
                )
                ->first();

        $running = 0;

        if ($last) {
            $parts =
                explode(
                    '-',
                    $last->case_no
                );

            $running =
                (int) end($parts);
        }

        return sprintf(
            'LSANK-3/2/%s-%04d',
            $year,
            $running + 1
        );
    }

    private function formatCase(
        LsankCriminalCase $case
    ): array {
        return [
            'criminal_case_id' =>
                $case->criminal_case_id,
            'case_id' =>
                $case->criminal_case_id,
            'case_no' =>
                $case->case_no,

            'legal_referral_id' =>
                $case->legal_referral_id,
            'notice_id' =>
                $case->notice_id,
            'user_id' =>
                $case->user_id,
            'application_id' =>
                $case->application_id,

            'efiling_case_no' =>
                $case->efiling_case_no,

            'license_id' =>
                $case->license_id,

            /*
             * For Pengabstrakan Air license_id can be null,
             * so prefer the value stored directly on the case.
             */
            'license_no' =>
                $case->license_no
                ?? $case->license?->license_no,

            'file_no' =>
                $case->file_no
                ?? $case->license?->file_no,

            'offence' =>
                $case->offence,
            'party_name' =>
                $case->party_name,
            'section_regulation' =>
                $case->section_regulation,

            'court_location' =>
                $case->court_location,
            'judge_name' =>
                $case->judge_name,

            'mention_date' =>
                optional(
                    $case->mention_date
                )?->format('Y-m-d'),

            'punishment' =>
                $case->punishment,

            'outstanding_amount' =>
                (float) (
                    $case->outstanding_amount
                    ?? 0
                ),

            'case_status' =>
                $case->case_status,
            'notes' =>
                $case->notes,

            'created_by' =>
                $case->created_by,
            'updated_by' =>
                $case->updated_by,

            'created_at' =>
                optional(
                    $case->created_at
                )?->toIso8601String(),

            'updated_at' =>
                optional(
                    $case->updated_at
                )?->toIso8601String(),
        ];
    }
}
