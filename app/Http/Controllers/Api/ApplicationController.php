<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function storeWaterApplication(Request $request)
    {
        $request->validate([
            'applicant_name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'required|email|max:255',
            'activity_type' => 'required|string|max:255',
        ]);

        $latestId = LsankApplication::max('application_id') ?? 0;

        $application = LsankApplication::create([
            'application_ref_no' => 'FAIL-' . now()->format('Y') . '-' . str_pad($latestId + 1, 4, '0', STR_PAD_LEFT),
            'user_id' => $request->user()->user_id,
            'applicant_name' => $request->applicant_name,
            'business_name' => $request->business_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'license_type' => 'Aktiviti Badan Perairan',
            'activity_type' => $request->activity_type,
            'application_type' => 'Baharu',
            'payment_status' => 'Selesai',
            'application_status' => 'Lulus',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan berjaya dihantar.',
            'application' => $application,
        ], 201);
    }

    public function myApplications(Request $request)
    {
        $applications = LsankApplication::where('user_id', $request->user()->user_id)
            ->latest('application_id')
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

    public function adminApplications()
    {
        $applications = LsankApplication::latest('application_id')->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

    public function show($id)
    {
        $application = LsankApplication::where('application_id', $id)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'application' => $application,
        ]);
    }
}