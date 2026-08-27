<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EffluentApplicationDocumentController extends Controller
{
    public function index(Request $request, $application)
    {
        $user = $request->user();

        $effluentApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$effluentApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documents = DB::table('lsank_application_documents')
            ->where('application_id', $effluentApplication->application_id)
            ->whereIn('status', [
                'uploaded',
                'verified',
                'rejected',
            ])
            ->orderByDesc('document_id')
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->document_id,
                    'document_id' => $document->document_id,

                    'document_key' => $document->remarks,

                    'name' => $document->file_name,
                    'original_name' => $document->file_name,
                    'file_name' => $document->file_name,

                    'file_path' => $document->file_path,
                    'path' => $document->file_path,

                    'file_type' => $document->file_type,
                    'mime_type' => $document->file_type,

                    'file_size' => (int) $document->file_size,
                    'size' => (int) $document->file_size,

                    'uploaded_by' => $document->uploaded_by,
                    'uploaded_at' => $document->uploaded_at,

                    'status' => $document->status,

                    'url' => asset(
                        'storage/' . ltrim($document->file_path, '/')
                    ),

                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    public function store(Request $request, $application)
    {
        $user = $request->user();

        $effluentApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$effluentApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $request->validate([
            'document_key' => ['required', 'string', 'max:100'],
            'files' => ['required'],
            'files.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ]);

        $documentKey = trim(
            (string) $request->input('document_key')
        );

        $uploadedDocuments = [];

        foreach ($request->file('files', []) as $file) {
            $path = $file->store(
                "applications/effluent/{$effluentApplication->application_id}/documents",
                'public'
            );

            $documentId = DB::table(
                'lsank_application_documents'
            )->insertGetId([
                'application_id' =>
                $effluentApplication->application_id,

                'document_type_id' => null,

                'file_name' =>
                $file->getClientOriginalName(),

                'file_path' => $path,

                'file_type' =>
                $file->getMimeType(),

                'file_size' =>
                $file->getSize(),

                'uploaded_by' =>
                $user->user_id,

                'uploaded_at' =>
                now(),

                'status' =>
                'uploaded',

                'remarks' =>
                $documentKey,

                'created_at' =>
                now(),

                'updated_at' =>
                now(),
            ], 'document_id');

            $uploadedDocuments[] = [
                'id' => $documentId,
                'document_id' => $documentId,

                'document_key' => $documentKey,

                'name' =>
                $file->getClientOriginalName(),

                'original_name' =>
                $file->getClientOriginalName(),

                'file_name' =>
                $file->getClientOriginalName(),

                'file_path' => $path,
                'path' => $path,

                'file_type' =>
                $file->getMimeType(),

                'mime_type' =>
                $file->getMimeType(),

                'file_size' =>
                $file->getSize(),

                'size' =>
                $file->getSize(),

                'uploaded_by' =>
                $user->user_id,

                'uploaded_at' =>
                now()->toDateTimeString(),

                'status' =>
                'uploaded',

                'url' => asset(
                    'storage/' . ltrim($path, '/')
                ),
            ];
        }

        $draftData = is_array($effluentApplication->draft_data)
            ? $effluentApplication->draft_data
            : [];

        $uploadedKeys =
            $draftData['uploaded_documents'] ?? [];

        if (!is_array($uploadedKeys)) {
            $uploadedKeys = [];
        }

        if (!in_array(
            $documentKey,
            $uploadedKeys,
            true
        )) {
            $uploadedKeys[] = $documentKey;
        }

        $draftData['uploaded_documents'] =
            array_values(
                array_unique($uploadedKeys)
            );

        $effluentApplication->draft_data =
            $draftData;

        $effluentApplication->save();

        return response()->json([
            'success' => true,
            'message' =>
            'Dokumen berjaya dimuat naik.',

            'document' =>
            count($uploadedDocuments) === 1
                ? $uploadedDocuments[0]
                : null,

            'data' =>
            count($uploadedDocuments) === 1
                ? $uploadedDocuments[0]
                : $uploadedDocuments,

            'documents' =>
            $uploadedDocuments,
        ]);
    }

    public function download(
        Request $request,
        $application,
        $document
    ) {
        $user = $request->user();

        $effluentApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$effluentApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documentRecord = DB::table(
            'lsank_application_documents'
        )
            ->where(
                'document_id',
                $document
            )
            ->where(
                'application_id',
                $effluentApplication->application_id
            )
            ->first();

        if (!$documentRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak dijumpai.',
            ], 404);
        }

        if (
            empty($documentRecord->file_path) ||
            !Storage::disk('public')->exists(
                $documentRecord->file_path
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Fail tidak dijumpai dalam storan.',
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($documentRecord->file_path),
            $documentRecord->file_name
        );
    }

    public function destroy(
        Request $request,
        $application,
        $document
    ) {
        $user = $request->user();

        $effluentApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$effluentApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documentRecord = DB::table(
            'lsank_application_documents'
        )
            ->where(
                'document_id',
                $document
            )
            ->where(
                'application_id',
                $effluentApplication->application_id
            )
            ->first();

        if (!$documentRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak dijumpai.',
            ], 404);
        }

        if (
            !empty($documentRecord->file_path) &&
            Storage::disk('public')->exists(
                $documentRecord->file_path
            )
        ) {
            Storage::disk('public')->delete(
                $documentRecord->file_path
            );
        }

        DB::table('lsank_application_documents')
            ->where(
                'document_id',
                $documentRecord->document_id
            )
            ->delete();

        $documentKey = trim(
            (string) ($documentRecord->remarks ?? '')
        );

        if ($documentKey !== '') {
            $remainingCount = DB::table(
                'lsank_application_documents'
            )
                ->where(
                    'application_id',
                    $effluentApplication->application_id
                )
                ->where(
                    'remarks',
                    $documentKey
                )
                ->count();

            if ($remainingCount === 0) {
                $draftData = is_array(
                    $effluentApplication->draft_data
                )
                    ? $effluentApplication->draft_data
                    : [];

                $uploadedKeys =
                    $draftData['uploaded_documents']
                    ?? [];

                if (is_array($uploadedKeys)) {
                    $draftData['uploaded_documents'] =
                        array_values(
                            array_filter(
                                $uploadedKeys,
                                fn($item) =>
                                (string) $item !==
                                    $documentKey
                            )
                        );

                    $effluentApplication->draft_data =
                        $draftData;

                    $effluentApplication->save();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' =>
            'Dokumen berjaya dipadam.',
        ]);
    }
}
