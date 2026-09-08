<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankAnnouncement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    // ============================================================
    // USER LIST
    // ============================================================

    public function index(Request $request)
    {
        /*
         * Sistem LSANK menggunakan waktu Malaysia.
         *
         * Database start_at / end_at disimpan sebagai waktu Malaysia,
         * jadi filter juga gunakan Asia/Kuala_Lumpur.
         */
        $now = Carbon::now('Asia/Kuala_Lumpur');

        $query = LsankAnnouncement::query()
            ->where('status', 'published')

            // Pengumuman sudah mula
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('start_at')
                    ->orWhere(
                        'start_at',
                        '<=',
                        $now->format('Y-m-d H:i:s')
                    );
            })

            // Pengumuman belum tamat
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('end_at')
                    ->orWhere(
                        'end_at',
                        '>=',
                        $now->format('Y-m-d H:i:s')
                    );
            });

        // ========================================================
        // OPTIONAL CATEGORY FILTER
        // ========================================================

        if ($request->filled('category')) {
            $category = trim(
                (string) $request->input('category')
            );

            if (
                $category !== '' &&
                strtolower($category) !== 'semua'
            ) {
                $query->where(
                    'category',
                    $category
                );
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

        // ========================================================
        // DEBUG
        // ========================================================

        Log::info('USER ANNOUNCEMENTS', [
            'now_malaysia' =>
            $now->format('Y-m-d H:i:s'),

            'count' =>
            $items->count(),

            'ids' =>
            $announcements
                ->pluck('announcement_id')
                ->values()
                ->all(),
        ]);

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    // ============================================================
    // USER DETAIL
    // ============================================================

    public function show(
        LsankAnnouncement $announcement
    ) {
        // Hanya published boleh dilihat user
        if ($announcement->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' =>
                'Pengumuman tidak dijumpai.',
            ], 404);
        }

        $now = Carbon::now(
            'Asia/Kuala_Lumpur'
        );

        // ========================================================
        // START DATE
        // ========================================================

        $startAt = null;

        if ($announcement->start_at !== null) {
            $startAt = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $announcement->start_at->format(
                    'Y-m-d H:i:s'
                ),
                'Asia/Kuala_Lumpur'
            );
        }

        // ========================================================
        // END DATE
        // ========================================================

        $endAt = null;

        if ($announcement->end_at !== null) {
            $endAt = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $announcement->end_at->format(
                    'Y-m-d H:i:s'
                ),
                'Asia/Kuala_Lumpur'
            );
        }

        // Belum bermula
        if (
            $startAt !== null &&
            $startAt->gt($now)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Pengumuman belum tersedia.',
            ], 404);
        }

        // Sudah tamat
        if (
            $endAt !== null &&
            $endAt->lt($now)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                'Pengumuman telah tamat.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' =>
            $this->formatAnnouncement(
                $announcement
            ),
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
