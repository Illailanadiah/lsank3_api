<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankEnquiry;
use App\Models\LsankNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminEnquiryController extends Controller
{
    // ============================================================
    // LIST
    // ============================================================

    public function index(Request $request)
    {
        $query = LsankEnquiry::query();

        // ========================================================
        // STATUS FILTER
        // ========================================================

        if (
            $request->filled('status') &&
            $request->status !== 'all'
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        // ========================================================
        // SEARCH
        // ========================================================

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            if ($search !== '') {
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
                            'phone_no',
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
        }

        // ========================================================
        // GET DATA
        // ========================================================

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

        DB::transaction(function () use (
            $enquiry,
            $validated
        ) {
            $enquiry->status =
                $validated['status'];

            $enquiry->save();

            // ====================================================
            // CLOSED
            // ====================================================

            if ($enquiry->status === 'closed') {
                $now = now();

                LsankNotification::query()
                    ->where(
                        'event_type',
                        'enquiry_created'
                    )
                    ->where(
                        'related_module',
                        'enquiry'
                    )
                    ->where(
                        'related_id',
                        $enquiry->enquiry_id
                    )
                    ->update([
                        'action_completed_at' =>
                        $now,

                        'is_read' =>
                        true,

                        'read_at' =>
                        $now,

                        'updated_at' =>
                        $now,
                    ]);
            }

            // ====================================================
            // REOPEN
            // ====================================================

            if (
                $enquiry->status === 'new' ||
                $enquiry->status === 'in_progress'
            ) {
                LsankNotification::query()
                    ->where(
                        'event_type',
                        'enquiry_created'
                    )
                    ->where(
                        'related_module',
                        'enquiry'
                    )
                    ->where(
                        'related_id',
                        $enquiry->enquiry_id
                    )
                    ->update([
                        'action_completed_at' =>
                        null,

                        'updated_at' =>
                        now(),
                    ]);
            }
        });

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
        $enquiryId =
            $enquiry->enquiry_id;

        DB::transaction(function () use (
            $enquiry,
            $enquiryId
        ) {
            /*
             * Padam notification berkaitan sekali.
             */
            LsankNotification::query()
                ->where(
                    'event_type',
                    'enquiry_created'
                )
                ->where(
                    'related_module',
                    'enquiry'
                )
                ->where(
                    'related_id',
                    $enquiryId
                )
                ->delete();

            $enquiry->delete();
        });

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

            /*
             * Database guna phone_no.
             * Flutter admin sekarang masih baca key "phone".
             * Return dua-dua supaya compatible.
             */
            'phone' =>
            $enquiry->phone_no,

            'phone_no' =>
            $enquiry->phone_no,

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
            )->format(
                'Y-m-d H:i:s'
            ),

            'updated_at' =>
            optional(
                $enquiry->updated_at
            )->format(
                'Y-m-d H:i:s'
            ),
        ];
    }

    // ============================================================
    // STATUS DISPLAY
    // ============================================================

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
