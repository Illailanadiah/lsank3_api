<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankLicense;
use App\Models\LsankReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionReportController extends Controller
{
    private const REPORT_TYPE = 'inspection_report';

    public function index(Request $request)
    {
        $query = LsankReport::query()
            ->where('report_type', self::REPORT_TYPE)
            ->latest('report_id');

        if ($request->filled('template_code')) {
            $templateCode = strtoupper(
                trim((string) $request->input('template_code'))
            );

            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.template_code')) = ?",
                [$templateCode]
            );
        }

        if ($request->filled('status')) {
            $status = strtolower(
                trim((string) $request->input('status'))
            );

            if (in_array($status, ['draft', 'draf'], true)) {
                $query->where('report_status', 'draft');
            } elseif (
                in_array(
                    $status,
                    ['submitted', 'direkodkan', 'selesai'],
                    true
                )
            ) {
                $query->where('report_status', 'submitted');
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('report_name', 'like', "%{$search}%")
                    ->orWhere('activity_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.holder_name')) LIKE ?",
                        ["%{$search}%"]
                    )
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.license_no')) LIKE ?",
                        ["%{$search}%"]
                    )
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.file_no')) LIKE ?",
                        ["%{$search}%"]
                    )
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.location')) LIKE ?",
                        ["%{$search}%"]
                    )
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(report_data, '$.prepared_by_name')) LIKE ?",
                        ["%{$search}%"]
                    );
            });
        }

        $reports = $query
            ->get()
            ->map(fn (LsankReport $report) => $this->formatReport($report))
            ->values();

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $userId = $this->currentUserId($request);
        $templateCode = strtoupper($validated['template_code']);
        $formData = $validated['form_data'];

        $report = DB::transaction(function () use (
            $templateCode,
            $formData,
            $userId
        ) {
            $summary = $this->extractSummary(
                $templateCode,
                $formData
            );

            $reportNo = $this->nextReportNumber();

            $reportData = array_merge(
                $summary,
                [
                    'report_no' => $reportNo,
                    'template_code' => $templateCode,
                    'form_data' => $formData,
                ]
            );

            return LsankReport::create([
                // Enforcement inspection reports are not necessarily tied
                // to an application, so application_id is intentionally null.
                'application_id' => null,

                'report_name' => $reportNo,
                'report_type' => self::REPORT_TYPE,
                'activity_name' =>
                    $summary['activity_name']
                    ?? $this->templateLabel($templateCode),

                // "Register / Simpan Laporan" creates a completed record.
                'report_status' => 'submitted',

                'description' =>
                    'Laporan Siasatan Tapak'
                    . (
                        !empty($summary['holder_name'])
                            ? ' - ' . $summary['holder_name']
                            : ''
                    ),

                'report_data' => $reportData,

                'submitted_at' => now(),
                'created_by' => $userId,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Laporan siasatan tapak berjaya disimpan.',
            'report' => $this->formatReport($report),
        ], 201);
    }

    public function show($report)
    {
        $record = $this->findInspectionReport($report);

        return response()->json([
            'success' => true,
            'report' => $this->formatReport($record),
        ]);
    }

    public function update(
        Request $request,
        $report
    ) {
        $record = $this->findInspectionReport($report);
        $validated = $this->validateRequest($request);

        $templateCode = strtoupper($validated['template_code']);
        $formData = $validated['form_data'];

        $summary = $this->extractSummary(
            $templateCode,
            $formData
        );

        $existingData = is_array($record->report_data)
            ? $record->report_data
            : [];

        $reportData = array_merge(
            $existingData,
            $summary,
            [
                'report_no' => $record->report_name,
                'template_code' => $templateCode,
                'form_data' => $formData,
            ]
        );

        $record->forceFill([
            'activity_name' =>
                $summary['activity_name']
                ?? $this->templateLabel($templateCode),

            'report_status' => 'submitted',

            'description' =>
                'Laporan Siasatan Tapak'
                . (
                    !empty($summary['holder_name'])
                        ? ' - ' . $summary['holder_name']
                        : ''
                ),

            'report_data' => $reportData,
            'submitted_at' => $record->submitted_at ?? now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Laporan siasatan tapak berjaya dikemas kini.',
            'report' => $this->formatReport($record->fresh()),
        ]);
    }

    public function cancel(
        Request $request,
        $report
    ) {
        $record = $this->findInspectionReport($report);

        if ($record->report_status === 'cancelled') {
            return response()->json([
                'success' => true,
                'message' => 'Laporan telah pun dibatalkan.',
                'report' => $this->formatReport($record),
            ]);
        }

        $reportData = is_array($record->report_data)
            ? $record->report_data
            : [];

        $reportData['cancelled_at'] =
            now()->toIso8601String();

        $reportData['cancelled_by'] =
            $this->currentUserId($request);

        $record->forceFill([
            'report_status' => 'cancelled',
            'report_data' => $reportData,
        ])->save();

        return response()->json([
            'success' => true,
            'message' =>
                'Laporan siasatan tapak berjaya dibatalkan.',
            'report' =>
                $this->formatReport($record->fresh()),
        ]);
    }
    public function destroy($report)
    {
        $record = $this->findInspectionReport($report);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Laporan siasatan tapak berjaya dipadam.',
        ]);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'template_code' => [
                'required',
                'string',
                'in:WA,WB,ED',
            ],
            'form_data' => [
                'required',
                'array',
            ],
        ]);
    }

    private function findInspectionReport($report): LsankReport
    {
        return LsankReport::query()
            ->where('report_type', self::REPORT_TYPE)
            ->where('report_id', (int) $report)
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

        return $userId > 0 ? $userId : null;
    }

    private function extractSummary(
        string $templateCode,
        array $formData
    ): array {
        $introduction = data_get(
            $formData,
            'A. Pengenalan',
            []
        );

        $facts = data_get(
            $formData,
            'B. Fakta Kes',
            []
        );

        $confirmation = data_get(
            $formData,
            'Pengesahan',
            []
        );

        $licenseId = data_get(
            $facts,
            'license_id'
        );

        $license = null;

        if ($licenseId !== null && $licenseId !== '') {
            $license = LsankLicense::query()
                ->with('application')
                ->find((int) $licenseId);
        }

        $coordinates = $this->parseCoordinates(
            (string) data_get(
                $introduction,
                'Koordinat',
                ''
            )
        );

        $inspectionDate = $this->parseDate(
            data_get(
                $facts,
                'Tarikh Kesalahan / Pemantauan'
            )
        );

        $offenceSection = $this->firstText([
            data_get(
                $facts,
                'Jenis Kesalahan (Seksyen)'
            ),
            data_get(
                $facts,
                'Jenis Kesalahan'
            ),
        ]);

        $holderName = $this->firstText([
            data_get(
                $facts,
                'Orang Kena Notis (OKN)'
            ),
            data_get(
                $facts,
                'Orang Kena Notis (OKN) / Isi Manual'
            ),
            data_get(
                $facts,
                'Orang Kena Notis (OKN) / No. Daftar'
            ),
            $license?->holder_name,
        ]);

        $registrationNo = $this->firstText([
            data_get(
                $facts,
                'No. Daftar / KP / SSM'
            ),
            data_get(
                $facts,
                'No Kad Pengenalan / No Syarikat'
            ),
            data_get(
                $license?->application,
                'registration_no'
            ),
            data_get(
                $license?->application,
                'company_registration_no'
            ),
            data_get(
                $license?->application,
                'identity_no'
            ),
        ]);

        $activityName = $this->firstText([
            data_get(
                $facts,
                'Kategori Aktiviti'
            ),
            $license?->activity_name,
            data_get(
                $license?->application,
                'activity_name'
            ),
            $this->templateLabel($templateCode),
        ]);

        $location = $this->firstText([
            data_get(
                $introduction,
                'Lokasi Kesalahan'
            ),
            $license?->activity_location,
            data_get(
                $license?->application,
                'activity_location'
            ),
        ]);

        return [
            'license_id' => $license?->license_id
                ?? (
                    $licenseId !== null && $licenseId !== ''
                        ? (int) $licenseId
                        : null
                ),

            'license_no' => $license?->license_no,
            'file_no' => $license?->file_no,

            'holder_name' => $holderName,
            'registration_no' => $registrationNo,

            'activity_name' => $activityName,
            'location' => $location,

            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],

            'inspection_date' => $inspectionDate,

            'inspection_type' => $this->firstText([
                data_get(
                    $introduction,
                    'Tujuan'
                ),
                $this->templateLabel($templateCode),
            ]),

            'inspection_result' =>
                $offenceSection !== null
                    ? 'Kesalahan Dikesan'
                    : 'Direkodkan',

            'offence_section' => $offenceSection,

            'prepared_by_name' => $this->firstText([
                data_get(
                    $confirmation,
                    'Disediakan Oleh'
                ),
            ]),

            'reviewed_by_name' => $this->firstText([
                data_get(
                    $confirmation,
                    'Disemak Oleh'
                ),
                'Khairi Khir (KBPK)',
            ]),
        ];
    }

    private function nextReportNumber(): string
    {
        $year = now()->format('Y');

        $last = LsankReport::query()
            ->where('report_type', self::REPORT_TYPE)
            ->where(
                'report_name',
                'like',
                "FAIL-{$year}-%"
            )
            ->lockForUpdate()
            ->latest('report_id')
            ->first();

        $running = 0;

        if ($last) {
            $parts = explode(
                '-',
                $last->report_name
            );

            $running = (int) end($parts);
        }

        return sprintf(
            'FAIL-%s-%04d',
            $year,
            $running + 1
        );
    }

    private function parseCoordinates(string $value): array
    {
        $parts = array_values(
            array_filter(
                array_map(
                    'trim',
                    preg_split('/[,;]/', $value) ?: []
                ),
                fn ($item) => $item !== ''
            )
        );

        if (count($parts) < 2) {
            return [
                'latitude' => null,
                'longitude' => null,
            ];
        }

        $latitude = is_numeric($parts[0])
            ? (float) $parts[0]
            : null;

        $longitude = is_numeric($parts[1])
            ? (float) $parts[1]
            : null;

        if (
            $latitude === null
            || $longitude === null
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
        ) {
            return [
                'latitude' => null,
                'longitude' => null,
            ];
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (
            preg_match(
                '/^(\d{2})\/(\d{2})\/(\d{4})$/',
                $raw,
                $matches
            )
        ) {
            return sprintf(
                '%04d-%02d-%02d',
                (int) $matches[3],
                (int) $matches[2],
                (int) $matches[1]
            );
        }

        try {
            return \Carbon\Carbon::parse(
                $raw
            )->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstText(array $values): ?string
    {
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $text = trim(
                (string) $value
            );

            if (
                $text !== ''
                && $text !== '-'
                && strtolower($text) !== 'null'
            ) {
                return $text;
            }
        }

        return null;
    }

    private function templateLabel(string $code): string
    {
        return match ($code) {
            'WA' => 'Pengabstrakan Air',
            'WB' => 'Aktiviti Badan Perairan',
            'ED' => 'Aktiviti Pelepasan Efluen',
            default => $code,
        };
    }

    private function formatReport(
        LsankReport $report
    ): array {
        $data = is_array($report->report_data)
            ? $report->report_data
            : [];

        return [
            'report_id' => $report->report_id,
            'report_no' => $report->report_name,

            'template_code' =>
                data_get(
                    $data,
                    'template_code'
                ),

            'license_id' =>
                data_get(
                    $data,
                    'license_id'
                ),

            'license_no' =>
                data_get(
                    $data,
                    'license_no'
                ),

            'file_no' =>
                data_get(
                    $data,
                    'file_no'
                ),

            'holder_name' =>
                data_get(
                    $data,
                    'holder_name'
                ),

            'registration_no' =>
                data_get(
                    $data,
                    'registration_no'
                ),

            'activity_name' =>
                data_get(
                    $data,
                    'activity_name',
                    $report->activity_name
                ),

            'location' =>
                data_get(
                    $data,
                    'location'
                ),

            'latitude' =>
                data_get(
                    $data,
                    'latitude'
                ),

            'longitude' =>
                data_get(
                    $data,
                    'longitude'
                ),

            'inspection_date' =>
                data_get(
                    $data,
                    'inspection_date'
                ),

            'inspection_type' =>
                data_get(
                    $data,
                    'inspection_type'
                ),

            'inspection_result' =>
                data_get(
                    $data,
                    'inspection_result'
                ),

            'offence_section' =>
                data_get(
                    $data,
                    'offence_section'
                ),

            // Frontend-friendly label.
            'report_status' =>
                $report->report_status === 'draft'
                    ? 'Draf'
                    : 'Direkodkan',

            'db_report_status' =>
                $report->report_status,

            'prepared_by_name' =>
                data_get(
                    $data,
                    'prepared_by_name'
                ),

            'reviewed_by_name' =>
                data_get(
                    $data,
                    'reviewed_by_name'
                ),

            'form_data' =>
                data_get(
                    $data,
                    'form_data',
                    []
                ),

            'created_by' => $report->created_by,

            'created_at' =>
                optional(
                    $report->created_at
                )?->toIso8601String(),

            'updated_at' =>
                optional(
                    $report->updated_at
                )?->toIso8601String(),
        ];
    }
}

