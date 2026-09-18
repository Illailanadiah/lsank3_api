<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankAnnouncement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\LsankNotification;
use App\Models\LsankUser;
use Illuminate\Support\Facades\DB;

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

        if ($announcement->status === 'published') {
            $this->createAnnouncementNotifications(
                $announcement
            );
        }

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

        $previousStatus =
            $announcement->status;
            
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

        if (
            $previousStatus !== 'published' &&
            $announcement->status === 'published'
        ) {
            $this->createAnnouncementNotifications(
                $announcement->fresh()
            );
        }

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
    // CREATE NOTIFICATIONS WHEN PUBLISHED
    // ============================================================

    private function createAnnouncementNotifications(
        LsankAnnouncement $announcement
    ): void {
        if ($announcement->status !== 'published') {
            return;
        }

        $users = LsankUser::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query
                    ->whereNull('user_type')
                    ->orWhereNotIn(
                        'user_type',
                        [
                            'admin',
                            'super_admin',
                        ]
                    );
            })
            ->get([
                'user_id',
            ]);

        if ($users->isEmpty()) {
            return;
        }

        DB::transaction(function () use (
            $users,
            $announcement
        ) {
            foreach ($users as $user) {
                $eventKey =
                    'announcement_published:'
                    . $announcement->announcement_id
                    . ':user:'
                    . $user->user_id;

                LsankNotification::firstOrCreate(
                    [
                        'event_key' => $eventKey,
                    ],
                    [
                        'user_id' =>
                        $user->user_id,

                        'title' =>
                        $announcement->title,

                        'message' =>
                        $announcement->message,

                        'notification_type' =>
                        'in_app',

                        'event_type' =>
                        'announcement_published',

                        'audience' =>
                        'user',

                        'severity' =>
                        $this->announcementSeverity(
                            $announcement
                        ),

                        'priority' =>
                        $this->announcementPriority(
                            $announcement
                        ),

                        'related_module' =>
                        'announcement',

                        'related_id' =>
                        $announcement->announcement_id,

                        'action_required' =>
                        false,

                        'action_label' =>
                        'Lihat Pengumuman',

                        'action_url' =>
                        '/announcements',

                        'show_as_ribbon' =>
                        false,

                        'ribbon_duration_seconds' =>
                        7,

                        'shown_count' =>
                        0,

                        'is_read' =>
                        false,

                        'expires_at' =>
                        $announcement->end_at,

                        'metadata' => [
                            'announcement_id' =>
                            $announcement->announcement_id,

                            'category' =>
                            $announcement->category,

                            'type' =>
                            $announcement->type,

                            'is_important' =>
                            (bool) $announcement->is_important,

                            'is_pinned' =>
                            (bool) $announcement->is_pinned,
                        ],

                        'sent_at' =>
                        Carbon::now('Asia/Kuala_Lumpur'),
                    ]
                );
            }
        });

        Log::info(
            'ANNOUNCEMENT NOTIFICATIONS CREATED',
            [
                'announcement_id' =>
                $announcement->announcement_id,

                'user_count' =>
                $users->count(),
            ]
        );
    }

    // ============================================================
    // NOTIFICATION SEVERITY
    // ============================================================

    private function announcementSeverity(
        LsankAnnouncement $announcement
    ): string {
        if (
            (bool) $announcement->is_important ||
            $announcement->type === 'important'
        ) {
            return 'critical';
        }

        return match ($announcement->type) {
            'warning',
            'maintenance' => 'warning',

            default => 'info',
        };
    }

    // ============================================================
    // NOTIFICATION PRIORITY
    // ============================================================

    private function announcementPriority(
        LsankAnnouncement $announcement
    ): int {
        if (
            (bool) $announcement->is_important ||
            $announcement->type === 'important'
        ) {
            return 1;
        }

        if (
            $announcement->type === 'warning' ||
            $announcement->type === 'maintenance'
        ) {
            return 2;
        }

        return 3;
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
