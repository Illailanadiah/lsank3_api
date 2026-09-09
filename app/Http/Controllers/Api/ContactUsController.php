<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankEnquiry;
use App\Models\LsankSupportContact;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    // ============================================================
    // CONTACT INFO
    // ============================================================

    public function info()
    {
        $contact = LsankSupportContact::query()
            ->orderBy('contact_id')
            ->first();

        if (!$contact) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'contact_id' => $contact->contact_id,
                'organization_name' => $contact->organization_name,
                'address' => $contact->address,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'website' => $contact->website,
                'operating_hours' => $contact->operating_hours,
                'map_url' => $contact->map_url,
            ],
        ]);
    }

    // ============================================================
    // USER ENQUIRIES
    // ============================================================

    public function index(Request $request)
    {
        $user = $request->user();

        $items = LsankEnquiry::query()
            ->where('user_id', $user->user_id)
            ->orderByDesc('enquiry_id')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $user = $request->user();

        $enquiry = LsankEnquiry::create([
            'user_id' => $user->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'subject' => trim($validated['subject']),
            'message' => trim($validated['message']),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pertanyaan berjaya dihantar.',
            'data' => $enquiry,
        ], 201);
    }

    public function show(
        Request $request,
        LsankEnquiry $enquiry
    ) {
        if (
            (int) $enquiry->user_id !==
            (int) $request->user()->user_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Akses tidak dibenarkan.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $enquiry,
        ]);
    }
}
