<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankEnquiry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminEnquiryController extends Controller
{
    // ============================================================
    // LIST
    // ============================================================

    public function index(Request $request)
    {
        $query = LsankEnquiry::query();

        if (
            $request->filled('status') &&
            $request->status !== 'all'
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($q) use ($search) {
                $q
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'subject',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'message',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $items = $query
            ->orderByRaw("
                CASE status
                    WHEN 'new' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'closed' THEN 3
                    ELSE 4
                END
            ")
            ->orderByDesc('enquiry_id')
            ->get()
            ->map(
                fn(LsankEnquiry $enquiry) =>
                $this->formatEnquiry($enquiry)
            )
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    // ============================================================
    // DETAIL
    // ============================================================

    public function show(
        LsankEnquiry $enquiry
    ) {
        return response()->json([
            'success' => true,
            'data' =>
            $this->formatEnquiry($enquiry),
        ]);
    }

    // ============================================================
    // UPDATE STATUS
    // ============================================================

    public function updateStatus(
        Request $request,
        LsankEnquiry $enquiry
    ) {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'new',
                    'in_progress',
                    'closed',
                ]),
            ],
        ]);

        $enquiry->status =
            $validated['status'];

        $enquiry->save();

        return response()->json([
            'success' => true,
            'message' =>
            'Status pertanyaan berjaya dikemas kini.',
            'data' =>
            $this->formatEnquiry(
                $enquiry->fresh()
            ),
        ]);
    }

    // ============================================================
    // DELETE
    // ============================================================

    public function destroy(
        LsankEnquiry $enquiry
    ) {
        $enquiry->delete();

        return response()->json([
            'success' => true,
            'message' =>
            'Pertanyaan berjaya dipadam.',
        ]);
    }

    // ============================================================
    // FORMAT
    // ============================================================

    private function formatEnquiry(
        LsankEnquiry $enquiry
    ): array {
        return [
            'id' =>
            $enquiry->enquiry_id,

            'enquiry_id' =>
            $enquiry->enquiry_id,

            'user_id' =>
            $enquiry->user_id,

            'name' =>
            $enquiry->name,

            'email' =>
            $enquiry->email,

            'phone' =>
            $enquiry->phone,

            'subject' =>
            $enquiry->subject,

            'message' =>
            $enquiry->message,

            'status' =>
            $enquiry->status,

            'status_display' =>
            $this->statusDisplay(
                $enquiry->status
            ),

            'created_at' =>
            optional(
                $enquiry->created_at
            )->format('Y-m-d H:i:s'),

            'updated_at' =>
            optional(
                $enquiry->updated_at
            )->format('Y-m-d H:i:s'),
        ];
    }

    private function statusDisplay(
        ?string $status
    ): string {
        return match ($status) {
            'new' =>
            'Baharu',

            'in_progress' =>
            'Dalam Tindakan',

            'closed' =>
            'Selesai',

            default =>
            '-',
        };
    }
}
