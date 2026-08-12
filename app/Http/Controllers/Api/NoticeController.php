<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LsankNotice::query();

        if ($request->filled('type')) {
            $query->where(
                'notice_type',
                strtoupper((string) $request->query('type'))
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                (string) $request->query('status')
            );
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->where(function ($subQuery) use ($search) {
                $like = "%{$search}%";

                $subQuery
                    ->where('notice_no', 'like', $like)
                    ->orWhere('okn_name', 'like', $like)
                    ->orWhere('identity_no', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('offence_section', 'like', $like)
                    ->orWhere('activity_category', 'like', $like)
                    ->orWhere('offence_details', 'like', $like);
            });
        }

        $notices = $query
            ->latest('notice_id')
            ->get()
            ->map(fn (LsankNotice $notice) => $this->formatNotice($notice))
            ->values();

        $summary = [
            'total' => LsankNotice::query()->count(),
            'npk' => LsankNotice::query()
                ->where('notice_type', 'NPK')
                ->count(),
            'npp' => LsankNotice::query()
                ->where('notice_type', 'NPP')
                ->count(),
            'n70' => LsankNotice::query()
                ->where('notice_type', 'N70')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'notices' => $notices,
            'summary' => $summary,
        ]);
    }

    public function nextNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_code' => [
                'required',
                'string',
                'max:20',
                Rule::in([
                    'NPKWA',
                    'NPKWB',
                    'NPKED',
                    'NPPWA',
                    'NPPWB',
                    'NPPED',
                    'N70',
                ]),
            ],
        ]);

        return response()->json([
            'success' => true,
            'notice_no' => $this->buildNextNoticeNo(
                $validated['template_code']
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $notice = DB::transaction(function () use ($request, $validated) {
            $formData = $validated['form_data'] ?? [];

            $core = $this->extractCoreFields($formData);

            $notice = LsankNotice::create([
                'notice_no' => $this->buildNextNoticeNo(
                    $validated['template_code'],
                    true
                ),
                'template_code' => $validated['template_code'],
                'notice_type' => $validated['notice_type'],
                'category' => $validated['category'] ?? null,
                'status' => $core['status'],
                'okn_name' => $core['okn_name'],
                'identity_no' => $core['identity_no'],
                'registered_address' => $core['registered_address'],
                'phone' => $core['phone'],
                'offence_section' => $core['offence_section'],
                'activity_category' => $core['activity_category'],
                'offence_details' => $core['offence_details'],
                'offence_location' => $core['offence_location'],
                'inspection_date' => $core['inspection_date'],
                'inspection_time' => $core['inspection_time'],
                'coordinates' => $core['coordinates'],
                'compound_amount' => $core['compound_amount'],
                'compound_status' => $core['compound_status'],
                'compound_due_date' => $core['compound_due_date'],
                'form_data' => $formData,
                'created_by' => $request->user()?->user_id,
                'updated_by' => $request->user()?->user_id,
                'issued_at' => now(),
            ]);

            return $notice;
        });

        return response()->json([
            'success' => true,
            'message' => 'Notis berjaya disimpan.',
            'notice' => $this->formatNotice($notice),
        ], 201);
    }

    public function show(LsankNotice $notice): JsonResponse
    {
        return response()->json([
            'success' => true,
            'notice' => $this->formatNotice($notice),
        ]);
    }

    public function update(
        Request $request,
        LsankNotice $notice
    ): JsonResponse {
        $validated = $this->validatePayload($request);

        $formData = $validated['form_data'] ?? [];
        $core = $this->extractCoreFields($formData);

        $notice->fill([
            'template_code' => $validated['template_code'],
            'notice_type' => $validated['notice_type'],
            'category' => $validated['category'] ?? null,
            'status' => $core['status'],
            'okn_name' => $core['okn_name'],
            'identity_no' => $core['identity_no'],
            'registered_address' => $core['registered_address'],
            'phone' => $core['phone'],
            'offence_section' => $core['offence_section'],
            'activity_category' => $core['activity_category'],
            'offence_details' => $core['offence_details'],
            'offence_location' => $core['offence_location'],
            'inspection_date' => $core['inspection_date'],
            'inspection_time' => $core['inspection_time'],
            'coordinates' => $core['coordinates'],
            'compound_amount' => $core['compound_amount'],
            'compound_status' => $core['compound_status'],
            'compound_due_date' => $core['compound_due_date'],
            'form_data' => $formData,
            'updated_by' => $request->user()?->user_id,
        ]);

        $notice->save();

        return response()->json([
            'success' => true,
            'message' => 'Notis berjaya dikemaskini.',
            'notice' => $this->formatNotice($notice->fresh()),
        ]);
    }

    public function updateStatus(
        Request $request,
        LsankNotice $notice
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'max:80',
            ],
        ]);

        $notice->forceFill([
            'status' => $validated['status'],
            'updated_by' => $request->user()?->user_id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Status notis berjaya dikemaskini.',
            'notice' => $this->formatNotice($notice->fresh()),
        ]);
    }

    public function destroy(LsankNotice $notice): JsonResponse
    {
        $notice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notis berjaya dipadam.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'template_code' => [
                'required',
                'string',
                'max:20',
                Rule::in([
                    'NPKWA',
                    'NPKWB',
                    'NPKED',
                    'NPPWA',
                    'NPPWB',
                    'NPPED',
                    'N70',
                ]),
            ],
            'notice_type' => [
                'required',
                'string',
                Rule::in([
                    'NPK',
                    'NPP',
                    'N70',
                ]),
            ],
            'category' => [
                'nullable',
                'string',
                'max:10',
            ],
            'form_data' => [
                'required',
                'array',
            ],
        ]);
    }

    private function buildNextNoticeNo(
        string $templateCode,
        bool $lock = false
    ): string {
        $year = now()->year;

        $query = LsankNotice::query()
            ->where('template_code', $templateCode)
            ->whereYear('created_at', $year)
            ->orderByDesc('notice_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $latest = $query->first();

        $sequence = 1;

        if ($latest !== null) {
            $parts = explode('-', (string) $latest->notice_no);
            $last = end($parts);

            if (is_numeric($last)) {
                $sequence = ((int) $last) + 1;
            }
        }

        return sprintf(
            '%s-%d-%04d',
            $templateCode,
            $year,
            $sequence
        );
    }

    private function extractCoreFields(array $formData): array
    {
        $get = function (
            string $section,
            string $label,
            mixed $default = null
        ) use ($formData): mixed {
            $sectionData = $formData[$section] ?? [];

            if (!is_array($sectionData)) {
                return $default;
            }

            $value = $sectionData[$label] ?? $default;

            if (is_string($value)) {
                $value = trim($value);
                return $value === '' ? $default : $value;
            }

            return $value;
        };

        $oknName =
            $get('Maklumat OKN', 'Orang Kena Notis (OKN)')
            ?? $get('Maklumat OKA', 'Orang Kena Arahan (OKA)');

        $identity =
            $get('Maklumat OKN', 'No Kad Pengenalan / No Syarikat')
            ?? $get('Maklumat OKA', 'No Kad Pengenalan / No Syarikat');

        $address =
            $get('Maklumat OKN', 'Alamat Berdaftar')
            ?? $get('Maklumat OKA', 'Alamat Berdaftar');

        $phone =
            $get('Maklumat OKN', 'No Telefon Yang Boleh Dihubungi')
            ?? $get('Maklumat OKA', 'No Telefon Yang Boleh Dihubungi');

        $inspectionDate =
            $get('Maklumat Pemeriksaan', 'Tarikh');

        $compoundDue =
            $get('Maklumat Kompaun', 'Tarikh Tamat Bayaran');

        return [
            'status' =>
                $get('Maklumat Kompaun', 'Status Kompaun')
                ?? 'Aktif',

            'okn_name' => $oknName,
            'identity_no' => $identity,
            'registered_address' => $address,
            'phone' => $phone,

            'offence_section' =>
                $get('Seksyen Kesalahan', 'Seksyen Kesalahan')
                ?? $get('Asas Arahan', 'Asas Arahan'),

            'activity_category' =>
                $get('Kategori Aktiviti', 'Kategori Aktiviti'),

            'offence_details' =>
                $get('Butiran Kesalahan', 'Butiran Kesalahan')
                ?? $get('Arahan', 'Arahan'),

            'offence_location' =>
                $get('Tempat Kesalahan', 'Tempat Kesalahan')
                ?? $get('Lokasi Kesalahan', 'Tempat Kesalahan'),

            'inspection_date' =>
                $this->parseUiDate($inspectionDate),

            'inspection_time' =>
                $get('Maklumat Pemeriksaan', 'Masa'),

            'coordinates' =>
                $get('Maklumat Pemeriksaan', 'Koordinat')
                ?? $get('Lokasi Kesalahan', 'Koordinat'),

            'compound_amount' =>
                $this->parseMoney(
                    $get('Maklumat Kompaun', 'Jumlah Kompaun (RM)')
                ),

            'compound_status' =>
                $get('Maklumat Kompaun', 'Status Kompaun'),

            'compound_due_date' =>
                $this->parseUiDate($compoundDue),
        ];
    }

    private function parseUiDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $parts = explode('/', $value);

        if (count($parts) === 3) {
            return sprintf(
                '%04d-%02d-%02d',
                (int) $parts[2],
                (int) $parts[1],
                (int) $parts[0]
            );
        }

        return $value;
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = preg_replace(
            '/[^0-9.\-]/',
            '',
            (string) $value
        );

        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function formatNotice(LsankNotice $notice): array
    {
        return [
            'notice_id' => $notice->notice_id,
            'notice_no' => $notice->notice_no,
            'template_code' => $notice->template_code,
            'notice_type' => $notice->notice_type,
            'category' => $notice->category,
            'status' => $notice->status,

            'okn_name' => $notice->okn_name,
            'identity_no' => $notice->identity_no,
            'registered_address' => $notice->registered_address,
            'phone' => $notice->phone,

            'offence_section' => $notice->offence_section,
            'activity_category' => $notice->activity_category,
            'offence_details' => $notice->offence_details,
            'offence_location' => $notice->offence_location,

            'inspection_date' => optional(
                $notice->inspection_date
            )?->format('Y-m-d'),

            'inspection_time' => $notice->inspection_time,
            'coordinates' => $notice->coordinates,

            'compound_amount' => $notice->compound_amount,
            'compound_status' => $notice->compound_status,

            'compound_due_date' => optional(
                $notice->compound_due_date
            )?->format('Y-m-d'),

            'form_data' => $notice->form_data ?? [],

            'issued_at' => optional(
                $notice->issued_at
            )?->toIso8601String(),

            'created_at' => optional(
                $notice->created_at
            )?->toIso8601String(),

            'updated_at' => optional(
                $notice->updated_at
            )?->toIso8601String(),
        ];
    }
}
