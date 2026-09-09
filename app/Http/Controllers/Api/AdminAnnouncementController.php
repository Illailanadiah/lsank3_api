<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankAnnouncement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    // ============================================================
    // LIST
    // ============================================================

    public function index(Request $request)
    {
        $query = LsankAnnouncement::query();

        // ========================================================
        // STATUS FILTER
        // ========================================================

        if (
            $request->filled('status') &&
            $request->input('status') !== 'all'
        ) {
            $query->where(
                'status',
                $request->input('status')
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
                    $q->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'message',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'category',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'type',
                            'like',
                            "%{$search}%"
                        );
                });
            }
        }

        // ========================================================
        // GET DATA
        // ========================================================

        $announcements = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_important')
            ->orderByDesc('start_at')
            ->orderByDesc('announcement_id')
            ->get();

        $items = $announcements
            ->map(function ($announcement) {
                return $this->formatAnnouncement(
                    $announcement
                );
            })
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    // ============================================================
    // CREATE
    // ============================================================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'type' => [
                'required',
                Rule::in([
                    'info',
                    'maintenance',
                    'warning',
                    'important',
                    'payment',
                    'general',
                ]),
            ],

            'status' => [
                'required',
                Rule::in([
                    'draft',
                    'published',
                    'archived',
                ]),
            ],

            'is_important' => [
                'nullable',
                'boolean',
            ],

            'is_pinned' => [
                'nullable',
                'boolean',
            ],

            'start_at' => [
                'nullable',
                'date',
            ],

            'end_at' => [
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
        ]);

        $user = $request->user();

        // ========================================================
        // DATE NORMALIZATION
        // ========================================================

        $startAt = null;

        if (!empty($validated['start_at'])) {
            $startAt = Carbon::parse(
                $validated['start_at']
            )
                ->timezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
        }

        $endAt = null;

        if (!empty($validated['end_at'])) {
            $endAt = Carbon::parse(
                $validated['end_at']
            )
                ->timezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
        }

        // ========================================================
        // CREATE
        // ========================================================

        $announcement = LsankAnnouncement::create([
            'title' =>
            trim($validated['title']),

            'message' =>
            trim($validated['message']),

            'category' =>
            trim($validated['category']),

            'type' =>
            $validated['type'],

            'status' =>
            $validated['status'],

            'is_important' =>
            $request->boolean(
                'is_important'
            ),

            'is_pinned' =>
            $request->boolean(
                'is_pinned'
            ),

            'start_at' =>
            $startAt,

            'end_at' =>
            $endAt,

            'created_by' =>
            $user?->user_id,
        ]);

        Log::info(
            'ADMIN ANNOUNCEMENT CREATED',
            [
                'announcement_id' =>
                $announcement->announcement_id,

                'created_by' =>
                $user?->user_id,

                'status' =>
                $announcement->status,
            ]
        );

        return response()->json([
            'success' => true,
            'message' =>
            'Pengumuman berjaya disimpan.',
            'data' =>
            $this->formatAnnouncement(
                $announcement->fresh()
            ),
        ], 201);
    }

    // ============================================================
    // DETAIL
    // ============================================================

    public function show(
        LsankAnnouncement $announcement
    ) {
        return response()->json([
            'success' => true,
            'data' =>
            $this->formatAnnouncement(
                $announcement
            ),
        ]);
    }

    // ============================================================
    // UPDATE
    // ============================================================

    public function update(
        Request $request,
        LsankAnnouncement $announcement
    ) {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'type' => [
                'required',
                Rule::in([
                    'info',
                    'maintenance',
                    'warning',
                    'important',
                    'payment',
                    'general',
                ]),
            ],

            'status' => [
                'required',
                Rule::in([
                    'draft',
                    'published',
                    'archived',
                ]),
            ],

            'is_important' => [
                'nullable',
                'boolean',
            ],

            'is_pinned' => [
                'nullable',
                'boolean',
            ],

            'start_at' => [
                'nullable',
                'date',
            ],

            'end_at' => [
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
        ]);

        // ========================================================
        // DATE NORMALIZATION
        // ========================================================

        $startAt = null;

        if (!empty($validated['start_at'])) {
            $startAt = Carbon::parse(
                $validated['start_at']
            )
                ->timezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
        }

        $endAt = null;

        if (!empty($validated['end_at'])) {
            $endAt = Carbon::parse(
                $validated['end_at']
            )
                ->timezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
        }

        // ========================================================
        // UPDATE
        // ========================================================

        $announcement->title =
            trim($validated['title']);

        $announcement->message =
            trim($validated['message']);

        $announcement->category =
            trim($validated['category']);

        $announcement->type =
            $validated['type'];

        $announcement->status =
            $validated['status'];

        $announcement->is_important =
            $request->boolean(
                'is_important'
            );

        $announcement->is_pinned =
            $request->boolean(
                'is_pinned'
            );

        $announcement->start_at =
            $startAt;

        $announcement->end_at =
            $endAt;

        $announcement->save();

        Log::info(
            'ADMIN ANNOUNCEMENT UPDATED',
            [
                'announcement_id' =>
                $announcement->announcement_id,

                'status' =>
                $announcement->status,
            ]
        );

        return response()->json([
            'success' => true,
            'message' =>
            'Pengumuman berjaya dikemas kini.',
            'data' =>
            $this->formatAnnouncement(
                $announcement->fresh()
            ),
        ]);
    }

    // ============================================================
    // DELETE
    // ============================================================

    public function destroy(
        LsankAnnouncement $announcement
    ) {
        $announcementId =
            $announcement->announcement_id;

        $announcement->delete();

        Log::info(
            'ADMIN ANNOUNCEMENT DELETED',
            [
                'announcement_id' =>
                $announcementId,
            ]
        );

        return response()->json([
            'success' => true,
            'message' =>
            'Pengumuman berjaya dipadam.',
        ]);
    }

    // ============================================================
    // FORMAT RESPONSE
    // ============================================================

    private function formatAnnouncement(
        LsankAnnouncement $announcement
    ): array {
        return [
            'id' =>
            $announcement->announcement_id,

            'announcement_id' =>
            $announcement->announcement_id,

            'title' =>
            $announcement->title,

            'message' =>
            $announcement->message,

            'category' =>
            $announcement->category,

            'type' =>
            $announcement->type,

            'status' =>
            $announcement->status,

            'is_important' =>
            (bool) $announcement->is_important,

            'is_pinned' =>
            (bool) $announcement->is_pinned,

            'start_at' =>
            $announcement->start_at
                ? $announcement->start_at
                ->format('Y-m-d H:i:s')
                : null,

            'end_at' =>
            $announcement->end_at
                ? $announcement->end_at
                ->format('Y-m-d H:i:s')
                : null,

            'created_by' =>
            $announcement->created_by,

            'created_at' =>
            $announcement->created_at
                ? $announcement->created_at
                ->copy()
                ->timezone(
                    'Asia/Kuala_Lumpur'
                )
                ->format(
                    'Y-m-d H:i:s'
                )
                : null,

            'updated_at' =>
            $announcement->updated_at
                ? $announcement->updated_at
                ->copy()
                ->timezone(
                    'Asia/Kuala_Lumpur'
                )
                ->format(
                    'Y-m-d H:i:s'
                )
                : null,
        ];
    }
}
