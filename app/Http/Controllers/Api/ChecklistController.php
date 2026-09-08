<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplicationType;
use App\Models\LsankChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChecklistController extends Controller
{
    public function index(Request $request)
    {
        $query = LsankChecklist::query()
            ->with('applicationType')
            ->where('status', 'active');

        if ($request->filled('application_type')) {
            $type = trim(
                (string) $request->input('application_type')
            );

            $typeId = $this->resolveApplicationTypeId($type);

            if ($typeId === null) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'Jenis permohonan tidak sah.',
                ], 422);
            }

            $query->where(
                'application_type_id',
                $typeId
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q
                    ->where(
                        'checklist_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $items = $query
            ->orderBy('application_type_id')
            ->orderBy('checklist_id')
            ->get()
            ->map(
                fn(LsankChecklist $checklist) =>
                $this->formatChecklist($checklist)
            )
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    public function show(
        Request $request,
        LsankChecklist $checklist
    ) {
        if ($checklist->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' =>
                'Senarai semak tidak dijumpai.',
            ], 404);
        }

        $checklist->load('applicationType');

        return response()->json([
            'success' => true,
            'data' =>
            $this->formatChecklist($checklist),
        ]);
    }

    public function download(
        Request $request,
        LsankChecklist $checklist
    ) {
        if ($checklist->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Senarai semak tidak dijumpai.',
            ], 404);
        }

        $path = trim(
            (string) $checklist->pdf_path
        );

        if ($path === '') {
            return response()->json([
                'success' => false,
                'message' => 'Fail senarai semak tidak tersedia.',
            ], 404);
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fail senarai semak tidak dijumpai.',
            ], 404);
        }

        $fullPath = $disk->path($path);

        $fileName = basename($path);

        return response()->download(
            $fullPath,
            $fileName,
            [
                'Content-Type' => mime_content_type($fullPath)
                    ?: 'application/octet-stream',
            ]
        );
    }
    
    private function resolveApplicationTypeId(
        string $value
    ): ?int {
        $normalized = strtolower(
            trim($value)
        );

        if (in_array(
            $normalized,
            [
                'water',
                'badan perairan',
                'aktiviti badan perairan',
            ],
            true
        )) {
            return LsankApplicationType::query()
                ->where(
                    'type_name',
                    'Aktiviti Badan Perairan'
                )
                ->value('application_type_id');
        }

        if (in_array(
            $normalized,
            [
                'effluent',
                'efluen',
                'pelepasan efluen',
                'aktiviti pelepasan efluen',
            ],
            true
        )) {
            return LsankApplicationType::query()
                ->where(
                    'type_name',
                    'Aktiviti Pelepasan Efluen'
                )
                ->value('application_type_id');
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return LsankApplicationType::query()
            ->where('type_code', $value)
            ->orWhere('type_name', $value)
            ->value('application_type_id');
    }

    private function formatChecklist(
        LsankChecklist $checklist
    ): array {
        $path = trim(
            (string) $checklist->pdf_path
        );

        return [
            'id' =>
            $checklist->checklist_id,

            'checklist_id' =>
            $checklist->checklist_id,

            'application_type_id' =>
            $checklist->application_type_id,

            'application_type' =>
            $checklist->applicationType?->type_name,

            'application_type_code' =>
            $checklist->applicationType?->type_code,

            'checklist_name' =>
            $checklist->checklist_name,

            'description' =>
            $checklist->description,

            'pdf_path' =>
            $path !== '' ? $path : null,

            'has_pdf' =>
            $path !== '',

            'status' =>
            $checklist->status,

            'created_at' =>
            optional(
                $checklist->created_at
            )->format('Y-m-d H:i:s'),

            'updated_at' =>
            optional(
                $checklist->updated_at
            )->format('Y-m-d H:i:s'),
        ];
    }
}
