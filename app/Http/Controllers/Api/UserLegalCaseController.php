<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserLegalCaseController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        abort_if($userId <= 0, 401);

        $civil = $this->civilCases($userId);
        $criminal = $this->criminalCases($userId);

        $items = $civil
            ->concat($criminal)
            ->sortByDesc(
                fn (array $item) =>
                    $item['created_at']
                    ?? $item['updated_at']
                    ?? ''
            )
            ->values();

        return response()->json([
            'success' => true,
            'legal_cases' => $items,
            'counts' => [
                'civil' => $civil->count(),
                'criminal' => $criminal->count(),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $type,
        $case
    ) {
        $userId = (int) (
            $request->user()->user_id
            ?? $request->user()->id
            ?? 0
        );

        abort_if($userId <= 0, 401);

        $type = strtolower(trim($type));
        $caseId = (int) $case;

        abort_unless(
            in_array(
                $type,
                ['civil', 'criminal'],
                true
            ),
            404
        );

        $record = $type === 'civil'
            ? $this
                ->civilCases($userId)
                ->firstWhere(
                    'case_id',
                    $caseId
                )
            : $this
                ->criminalCases($userId)
                ->firstWhere(
                    'case_id',
                    $caseId
                );

        abort_if(!$record, 404);

        return response()->json([
            'success' => true,
            'legal_case' => $record,
        ]);
    }

    private function civilCases(
        int $userId
    ): Collection {
        return DB::table(
            'lsank_civil_cases as c'
        )
            ->leftJoin(
                'lsank_licenses as l',
                'l.license_id',
                '=',
                'c.license_id'
            )
            ->leftJoin(
                'lsank_applications as a',
                'a.application_id',
                '=',
                'l.application_id'
            )
            ->where(
                function ($query) use (
                    $userId
                ) {
                    $query
                        ->where(
                            'c.user_id',
                            $userId
                        )
                        ->orWhere(
                            'a.user_id',
                            $userId
                        );
                }
            )
            ->select([
                'c.civil_case_id',
                'c.case_no',
                'c.user_id',
                'c.license_id',
                'c.application_id',
                'c.file_no',
                'c.offence',
                'c.party_name',
                'c.section_regulation',
                'c.court_location',
                'c.judge_name',
                'c.punishment',
                'c.outstanding_amount',
                'c.case_status',
                'c.notes',
                'c.created_at',
                'c.updated_at',
                'l.license_no as linked_license_no',
                'l.file_no as linked_file_no',
            ])
            ->orderByDesc(
                'c.civil_case_id'
            )
            ->get()
            ->map(
                function ($row) {
                    return [
                        'case_type' =>
                            'civil',
                        'case_id' =>
                            (int) $row
                                ->civil_case_id,
                        'case_no' =>
                            $row->case_no,
                        'user_id' =>
                            $row->user_id,
                        'license_id' =>
                            $row->license_id,
                        'application_id' =>
                            $row->application_id,
                        'license_no' =>
                            $row
                                ->linked_license_no,
                        'file_no' =>
                            $row->file_no
                            ?? $row
                                ->linked_file_no,
                        'efiling_case_no' =>
                            null,
                        'offence' =>
                            $row->offence,
                        'party_name' =>
                            $row->party_name,
                        'section_regulation' =>
                            $row
                                ->section_regulation,
                        'court_location' =>
                            $row
                                ->court_location,
                        'judge_name' =>
                            $row->judge_name,
                        'mention_date' =>
                            null,
                        'punishment' =>
                            $row->punishment,
                        'outstanding_amount' =>
                            (float) (
                                $row
                                    ->outstanding_amount
                                ?? 0
                            ),
                        'case_status' =>
                            $row->case_status,
                        'notes' =>
                            $row->notes,
                        'created_at' =>
                            $row->created_at,
                        'updated_at' =>
                            $row->updated_at,
                    ];
                }
            );
    }

    private function criminalCases(
        int $userId
    ): Collection {
        return DB::table(
            'lsank_criminal_cases as c'
        )
            ->leftJoin(
                'lsank_licenses as l',
                'l.license_id',
                '=',
                'c.license_id'
            )
            ->leftJoin(
                'lsank_applications as a',
                'a.application_id',
                '=',
                'l.application_id'
            )
            ->where(
                function ($query) use (
                    $userId
                ) {
                    $query
                        ->where(
                            'c.user_id',
                            $userId
                        )
                        ->orWhere(
                            'a.user_id',
                            $userId
                        );
                }
            )
            ->select([
                'c.criminal_case_id',
                'c.case_no',
                'c.user_id',
                'c.license_id',
                'c.application_id',
                'c.file_no',
                'c.license_no',
                'c.efiling_case_no',
                'c.offence',
                'c.party_name',
                'c.section_regulation',
                'c.court_location',
                'c.judge_name',
                'c.mention_date',
                'c.punishment',
                'c.outstanding_amount',
                'c.case_status',
                'c.notes',
                'c.created_at',
                'c.updated_at',
                'l.license_no as linked_license_no',
                'l.file_no as linked_file_no',
            ])
            ->orderByDesc(
                'c.criminal_case_id'
            )
            ->get()
            ->map(
                function ($row) {
                    return [
                        'case_type' =>
                            'criminal',
                        'case_id' =>
                            (int) $row
                                ->criminal_case_id,
                        'case_no' =>
                            $row->case_no,
                        'user_id' =>
                            $row->user_id,
                        'license_id' =>
                            $row->license_id,
                        'application_id' =>
                            $row->application_id,
                        'license_no' =>
                            $row->license_no
                            ?? $row
                                ->linked_license_no,
                        'file_no' =>
                            $row->file_no
                            ?? $row
                                ->linked_file_no,
                        'efiling_case_no' =>
                            $row
                                ->efiling_case_no,
                        'offence' =>
                            $row->offence,
                        'party_name' =>
                            $row->party_name,
                        'section_regulation' =>
                            $row
                                ->section_regulation,
                        'court_location' =>
                            $row
                                ->court_location,
                        'judge_name' =>
                            $row->judge_name,
                        'mention_date' =>
                            $row
                                ->mention_date,
                        'punishment' =>
                            $row->punishment,
                        'outstanding_amount' =>
                            (float) (
                                $row
                                    ->outstanding_amount
                                ?? 0
                            ),
                        'case_status' =>
                            $row->case_status,
                        'notes' =>
                            $row->notes,
                        'created_at' =>
                            $row->created_at,
                        'updated_at' =>
                            $row->updated_at,
                    ];
                }
            );
    }
}
