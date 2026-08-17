<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankLegalReferral;
use App\Services\LegalEscalationService;
use Illuminate\Http\Request;

class LegalReferralController extends Controller
{
    public function index(
        Request $request,
        LegalEscalationService $service
    ) {
        $query =
            LsankLegalReferral::query()
                ->latest('legal_referral_id');

        if (
            $request->filled('case_track')
        ) {
            $query->where(
                'case_track',
                $request->string(
                    'case_track'
                )->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'referral_status',
                $request->string(
                    'status'
                )->toString()
            );
        }

        $items = $query
            ->get()
            ->map(
                fn (
                    LsankLegalReferral $item
                ) => $service->format($item)
            )
            ->values();

        return response()->json([
            'success' => true,
            'referrals' => $items,
        ]);
    }

    public function show(
        $referral,
        LegalEscalationService $service
    ) {
        $item =
            LsankLegalReferral::query()
                ->findOrFail(
                    (int) $referral
                );

        return response()->json([
            'success' => true,
            'referral' =>
                $service->format($item),
        ]);
    }

    public function myStatus(
        Request $request,
        LegalEscalationService $service
    ) {
        return response()->json([
            'success' => true,
            'legal_restriction' =>
                $service->summaryForUser(
                    (int) $request
                        ->user()
                        ->user_id
                ),
        ]);
    }

    public function updateStatus(
        Request $request,
        $referral
    ) {
        $validated =
            $request->validate([
                'status' => [
                    'required',
                    'in:triggered,in_progress,resolved,cancelled',
                ],
            ]);

        $item =
            LsankLegalReferral::query()
                ->findOrFail(
                    (int) $referral
                );

        $item->forceFill([
            'referral_status' =>
                $validated['status'],
        ])->save();

        return response()->json([
            'success' => true,
            'message' =>
                'Status rujukan Perundangan berjaya dikemas kini.',
            'referral' => $item->fresh(),
        ]);
    }
}
