<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WaterApplicationDocumentController extends Controller
{
    public function index(Request $request, $application)
    {
        $user = $request->user();

        $waterApplication = DB::table('lsank_water_applications')
            ->where('id', $application)
            ->where('user_id', $user->id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documents = DB::table('lsank_application_documents')
            ->where('application_id', $application)
            ->where('application_type', 'water')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    public function store(Request $request, $application)
    {
        $user = $request->user();

        $waterApplication = DB::table('lsank_water_applications')
            ->where('id', $application)
            ->where('user_id', $user->id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $request->validate([
            'document_key' => 'required|string|max:100',
            'files' => 'required',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $documentKey = $request->input('document_key');

        $uploadedDocuments = [];

        foreach ($request->file('files', []) as $file) {
            $path = $file->store(
                "applications/water/{$application}/documents",
                'public'
            );

            $documentId = DB::table('lsank_application_documents')->insertGetId([
                'application_id' => $application,
                'application_type' => 'water',
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
                'document_key' => $documentKey,
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $draftData = [];

        if (!empty($waterApplication->draft_data)) {
            $decoded = json_decode($waterApplication->draft_data, true);

            if (is_array($decoded)) {
                $draftData = $decoded;
            }
        }

        $currentUploadedDocuments =
            $draftData['uploaded_documents'] ?? [];

        if (!is_array($currentUploadedDocuments)) {
            $currentUploadedDocuments = [];
        }

        if (!in_array($documentKey, $currentUploadedDocuments, true)) {
            $currentUploadedDocuments[] = $documentKey;
        }

        $draftData['uploaded_documents'] = $currentUploadedDocuments;

        DB::table('lsank_water_applications')
            ->where('id', $application)
            ->update([
                'draft_data' => json_encode($draftData),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berjaya dimuat naik.',
            'data' => $uploadedDocuments,
        ]);
    }

    public function destroy(Request $request, $application, $document)
    {
        $user = $request->user();

        $waterApplication = DB::table('lsank_water_applications')
            ->where('id', $application)
            ->where('user_id', $user->id)
            ->first();

        if (!$waterApplication) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $documentRecord = DB::table('lsank_application_documents')
            ->where('id', $document)
            ->where('application_id', $application)
            ->where('application_type', 'water')
            ->first();

        if (!$documentRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak dijumpai.',
            ], 404);
        }

        if (
            !empty($documentRecord->file_path) &&
            Storage::disk('public')->exists($documentRecord->file_path)
        ) {
            Storage::disk('public')->delete($documentRecord->file_path);
        }

        DB::table('lsank_application_documents')
            ->where('id', $document)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berjaya dipadam.',
        ]);
    }
}
