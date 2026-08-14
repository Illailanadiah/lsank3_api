<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankCivilCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CivilCaseController extends Controller
{
    public function index(Request $request)
    {
        $query = LsankCivilCase::query()
            ->with([
                'license:license_id,license_no,file_no,holder_name,license_type,activity_name',
            ])
            ->latest('civil_case_id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('case_no', 'like', "%{$search}%")
                    ->orWhere('file_no', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('offence', 'like', "%{$search}%")
                    ->orWhere('section_regulation', 'like', "%{$search}%")
                    ->orWhere('court_location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'case_status',
                trim((string) $request->input('status'))
            );
        }

        if ($request->filled('offence')) {
            $query->where(
                'offence',
                trim((string) $request->input('offence'))
            );
        }

        return response()->json([
            'success' => true,
            'cases' => $query
                ->get()
                ->map(fn (LsankCivilCase $case) => $this->formatCase($case))
                ->values(),
        ]);
    }

    public function nextNumber()
    {
        return response()->json([
            'success' => true,
            'case_no' => $this->peekNextCaseNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $case = DB::transaction(function () use ($request, $validated) {
            return LsankCivilCase::create([
                ...$validated,
                'case_no' => $this->nextCaseNumber(),
                'created_by' => $this->currentUserId($request),
                'updated_by' => $this->currentUserId($request),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Kes sivil berjaya disimpan.',
            'case' => $this->formatCase($case->fresh('license')),
        ], 201);
    }

    public function show($case)
    {
        $record = $this->findCase($case);

        return response()->json([
            'success' => true,
            'case' => $this->formatCase($record),
        ]);
    }

    public function update(
        Request $request,
        $case
    ) {
        $record = $this->findCase($case);
        $validated = $this->validateRequest($request);

        $record->forceFill([
            ...$validated,
            'updated_by' => $this->currentUserId($request),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Kes sivil berjaya dikemas kini.',
            'case' => $this->formatCase($record->fresh('license')),
        ]);
    }

    public function destroy($case)
    {
        $record = $this->findCase($case);

        $caseNo = $record->case_no;

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => "Kes sivil {$caseNo} berjaya dipadam.",
        ]);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'license_id' => [
                'nullable',
                'integer',
                'exists:lsank_licenses,license_id',
            ],

            'file_no' => [
                'nullable',
                'string',
                'max:100',
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

    private function findCase($case): LsankCivilCase
    {
        return LsankCivilCase::query()
            ->with('license')
            ->where(
                'civil_case_id',
                (int) $case
            )
            ->firstOrFail();
    }

    private function currentUserId(Request $request): ?int
    {
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
        $year = now()->format('Y');

        $last = LsankCivilCase::query()
            ->where(
                'case_no',
                'like',
                "LSANK-3/2/{$year}-%"
            )
            ->lockForUpdate()
            ->latest('civil_case_id')
            ->first();

        $running = 0;

        if ($last) {
            $parts = explode(
                '-',
                $last->case_no
            );

            $running = (int) end($parts);
        }

        return sprintf(
            'LSANK-3/2/%s-%04d',
            $year,
            $running + 1
        );
    }

    private function peekNextCaseNumber(): string
    {
        $year = now()->format('Y');

        $last = LsankCivilCase::query()
            ->where(
                'case_no',
                'like',
                "LSANK-3/2/{$year}-%"
            )
            ->latest('civil_case_id')
            ->first();

        $running = 0;

        if ($last) {
            $parts = explode(
                '-',
                $last->case_no
            );

            $running = (int) end($parts);
        }

        return sprintf(
            'LSANK-3/2/%s-%04d',
            $year,
            $running + 1
        );
    }

    private function formatCase(
        LsankCivilCase $case
    ): array {
        return [
            'civil_case_id' => $case->civil_case_id,
            'case_id' => $case->civil_case_id,
            'case_no' => $case->case_no,

            'license_id' => $case->license_id,
            'license_no' => $case->license?->license_no,
            'file_no' => $case->file_no
                ?? $case->license?->file_no,

            'offence' => $case->offence,
            'party_name' => $case->party_name,
            'section_regulation' => $case->section_regulation,

            'court_location' => $case->court_location,
            'judge_name' => $case->judge_name,

            'punishment' => $case->punishment,
            'outstanding_amount' => (float) $case->outstanding_amount,
            'case_status' => $case->case_status,

            'notes' => $case->notes,

            'created_by' => $case->created_by,
            'updated_by' => $case->updated_by,

            'created_at' =>
                optional($case->created_at)?->toIso8601String(),

            'updated_at' =>
                optional($case->updated_at)?->toIso8601String(),
        ];
    }
}
