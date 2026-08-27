<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WaterApplicationDocumentController extends Controller
{
    public function index(Request $request, $application)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak sah.',
            ], 401);
        }

        $waterApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documents = DB::table('lsank_application_documents')
            ->where('application_id', $application)
            ->orderByDesc('id')
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'document_id' => $document->id,
                    'document_key' => $document->document_key,
                    'name' => $document->original_name,
                    'original_name' => $document->original_name,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'path' => $document->file_path,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'size' => $document->file_size,
                'url' => asset('storage/' . $document->file_path),
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $documents,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request, $application)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak sah.',
            ], 401);
        }

        $waterApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $request->validate([
            'document_key' => [
                'required',
                'string',
                'max:100',
            ],
            'files' => [
                'required',
                'array',
                'min:1',
            ],
            'files.*' => [
                'required',
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
                "applications/water/{$application}/documents",
                'public'
            );

            $documentId = DB::table('lsank_application_documents')->insertGetId([
                'application_id' => $waterApplication->application_id,
                'document_key' => $documentKey,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $uploadedDocuments[] = [
                'id' => $documentId,
                'document_id' => $documentId,
                'document_key' => $documentKey,

                'name' => $file->getClientOriginalName(),
                'original_name' => $file->getClientOriginalName(),

                'file_name' => basename($path),

                'file_path' => $path,
                'path' => $path,

                'mime_type' => $file->getMimeType(),

                'file_size' => $file->getSize(),
                'size' => $file->getSize(),

                'url' => asset('storage/' . $path),
            ];
        }

        /*
         * Simpan document key dalam draft_data application.
         *
         * Jangan overwrite draft_data sedia ada.
         */
        $draftData = is_array($waterApplication->draft_data)
            ? $waterApplication->draft_data
            : [];

        $currentUploadedDocuments =
            $draftData['uploaded_documents'] ?? [];

        if (!is_array($currentUploadedDocuments)) {
            $currentUploadedDocuments = [];
        }

        if (
            !in_array(
                $documentKey,
                $currentUploadedDocuments,
                true
            )
        ) {
            $currentUploadedDocuments[] = $documentKey;
        }

        $draftData['uploaded_documents'] =
            array_values(
                array_unique($currentUploadedDocuments)
            );

        $waterApplication->draft_data = $draftData;
        $waterApplication->save();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berjaya dimuat naik.',

            /*
             * Frontend awak kadang-kadang baca `document`
             * dan kadang-kadang baca `data`.
             */
            'document' =>
            count($uploadedDocuments) === 1
                ? $uploadedDocuments[0]
                : null,

            'documents' => $uploadedDocuments,

            'data' =>
            count($uploadedDocuments) === 1
                ? $uploadedDocuments[0]
                : $uploadedDocuments,
        ]);
    }

    public function destroy(
        Request $request,
        $application,
        $document
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak sah.',
            ], 401);
        }

        $waterApplication = LsankApplication::query()
            ->where('application_id', $application)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documentRecord = DB::table('lsank_application_documents')
            ->where('id', $document)
            ->where(
                'application_id',
                $waterApplication->application_id
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
            ->where('id', $document)
            ->delete();

        /*
         * Semak sama ada document_key tersebut
         * masih mempunyai fail lain.
         */
        $remainingDocumentCount =
            DB::table('lsank_application_documents')
            ->where(
                'application_id',
                $waterApplication->application_id
            )
            ->where(
                'document_key',
                $documentRecord->document_key
            )
            ->count();

        if ($remainingDocumentCount === 0) {
            $draftData = is_array(
                $waterApplication->draft_data
            )
                ? $waterApplication->draft_data
                : [];

            $uploadedDocuments =
                $draftData['uploaded_documents'] ?? [];

            if (is_array($uploadedDocuments)) {
                $uploadedDocuments = array_values(
                    array_filter(
                        $uploadedDocuments,
                        fn($key) =>
                        (string) $key !==
                            (string) $documentRecord->document_key
                    )
                );

                $draftData['uploaded_documents'] =
                    $uploadedDocuments;

                $waterApplication->draft_data =
                    $draftData;

                $waterApplication->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berjaya dipadam.',
        ]);
    }
}
