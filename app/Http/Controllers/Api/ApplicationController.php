<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankApplication;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | User Side - Store Water Application
    |--------------------------------------------------------------------------
    */

    public function storeWaterApplication(Request $request)
    {
        $request->validate([
            'applicant_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'activity_type' => 'nullable|string|max:255',
        ]);

        $application = LsankApplication::create([
            'application_ref_no' => $this->generateReferenceNo(),
            'user_id' => $request->user()->user_id,

            'applicant_name' => $request->applicant_name,
            'business_name' => $request->business_name,
            'phone' => $request->phone,
            'email' => $request->email,

            'license_type' => 'Aktiviti Badan Perairan',
            'activity_type' => $request->activity_type ?? 'Tidak Dinyatakan',
            'application_type' => 'Baharu',

            'payment_status' => 'Belum Bayar',
            'application_status' => 'Baharu',

            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan badan perairan berjaya dihantar.',
            'application' => $application,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | User Side - My Applications
    |--------------------------------------------------------------------------
    */

    public function myApplications(Request $request)
    {
        $applications = LsankApplication::where('user_id', $request->user()->user_id)
            ->orderByDesc('application_id')
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }
/*
|--------------------------------------------------------------------------
| Admin Side - All Applications
|--------------------------------------------------------------------------
*/

private function applicationBaseQuery()
{
    return LsankApplication::with([
        'applicant',
        'type',
        'status',
        'waterBody',
        'effluent',
    ]);
}

public function adminApplications()
{
    $applications = $this->applicationBaseQuery()
        ->orderByDesc('application_id')
        ->get();

    return response()->json([
        'success' => true,
        'applications' => $applications,
    ]);
}

public function adminWaterApplications()
{
    $applications = $this->applicationBaseQuery()
        ->where(function ($query) {
            $query->where('license_type', 'Aktiviti Badan Perairan')
                ->orWhere('application_type_id', 1)
                ->orWhereHas('type', function ($typeQuery) {
                    $typeQuery->where('type_code', 'WATER');
                })
                ->orWhereHas('waterBody');
        })
        ->orderByDesc('application_id')
        ->get();

    return response()->json([
        'success' => true,
        'applications' => $applications,
    ]);
}

public function adminEffluentApplications()
{
    $applications = $this->applicationBaseQuery()
        ->where(function ($query) {
            $query->where('license_type', 'Aktiviti Pelepasan Efluen')
                ->orWhere('application_type_id', 2)
                ->orWhereHas('type', function ($typeQuery) {
                    $typeQuery->where('type_code', 'EFFLUENT');
                })
                ->orWhereHas('effluent');
        })
        ->orderByDesc('application_id')
        ->get();

    return response()->json([
        'success' => true,
        'applications' => $applications,
    ]);
}

/*
|--------------------------------------------------------------------------
| Admin Side - Application Detail
|--------------------------------------------------------------------------
*/

public function show($id)
{
    $application = $this->applicationBaseQuery()
        ->where('application_id', $id)
        ->first();

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

public function showWater($id)
{
    $application = $this->applicationBaseQuery()
        ->where('application_id', $id)
        ->where(function ($query) {
            $query->where('license_type', 'Aktiviti Badan Perairan')
                ->orWhere('application_type_id', 1)
                ->orWhereHas('type', function ($typeQuery) {
                    $typeQuery->where('type_code', 'WATER');
                })
                ->orWhereHas('waterBody');
        })
        ->first();

    if (!$application) {
        return response()->json([
            'success' => false,
            'message' => 'Permohonan badan perairan tidak dijumpai.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'application' => $application,
    ]);
}

public function showEffluent($id)
{
    $application = $this->applicationBaseQuery()
        ->where('application_id', $id)
        ->where(function ($query) {
            $query->where('license_type', 'Aktiviti Pelepasan Efluen')
                ->orWhere('application_type_id', 2)
                ->orWhereHas('type', function ($typeQuery) {
                    $typeQuery->where('type_code', 'EFFLUENT');
                })
                ->orWhereHas('effluent');
        })
        ->first();

    if (!$application) {
        return response()->json([
            'success' => false,
            'message' => 'Permohonan efluen tidak dijumpai.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'application' => $application,
    ]);
}
    /*
    |--------------------------------------------------------------------------
    | Admin Side - Status / Review
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'application_status' => 'required|string|max:100',
            'payment_status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        $application = LsankApplication::where('application_id', $id)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $application->update([
            'application_status' => $request->application_status,
            'payment_status' => $request->payment_status ?? $application->payment_status,
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status permohonan berjaya dikemaskini.',
            'application' => $application,
        ]);
    }

    public function review(Request $request, $id)
    {
        $request->validate([
            'application_status' => 'nullable|string|max:100',
            'payment_status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        $application = LsankApplication::where('application_id', $id)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan tidak dijumpai.',
            ], 404);
        }

        $application->update([
            'application_status' => $request->application_status ?? 'Dalam Semakan',
            'payment_status' => $request->payment_status ?? $application->payment_status,
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Semakan permohonan berjaya disimpan.',
            'application' => $application,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function generateReferenceNo()
    {
        $latestId = LsankApplication::max('application_id') ?? 0;

        return 'FAIL-' . now()->format('Y') . '-' . str_pad($latestId + 1, 4, '0', STR_PAD_LEFT);
    }
}