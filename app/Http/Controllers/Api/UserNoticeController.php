<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankNoticeRestriction;
use App\Services\NoticeRestrictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserNoticeController extends Controller
{
    public function index(
        Request $request,
        NoticeRestrictionService $restrictions
    ) {
        $userId = (int) $request->user()->user_id;

        // Fix for existing notices that were created before the restriction
        // observer/table was introduced.
        $synced = $restrictions
            ->syncExistingNoticesForUser($userId);

        $rows = DB::table('lsank_notices as n')
            ->join(
                'lsank_notice_restrictions as r',
                'r.notice_id',
                '=',
                'n.notice_id'
            )
            ->where('r.user_id', $userId)
            ->orderByDesc('n.notice_id')
            ->select('n.*')
            ->get()
            ->map(
                fn ($row) =>
                    $this->formatNotice(
                        (array) $row,
                        $restrictions,
                        $userId
                    )
            )
            ->values();

        return response()->json([
            'success' => true,
            'notices' => $rows,
            'synced_existing_notices' => $synced,
            'notice_restriction' =>
                $restrictions->summaryForUser($userId),
        ]);
    }

    public function show(
        Request $request,
        $notice,
        NoticeRestrictionService $restrictions
    ) {
        $userId = (int) $request->user()->user_id;

        $restrictions->syncExistingNoticesForUser(
            $userId
        );

        $restriction =
            LsankNoticeRestriction::query()
                ->where('notice_id', (int) $notice)
                ->where('user_id', $userId)
                ->firstOrFail();

        $record = DB::table('lsank_notices')
            ->where(
                'notice_id',
                $restriction->notice_id
            )
            ->first();

        abort_if(!$record, 404);

        return response()->json([
            'success' => true,
            'notice' => $this->formatNotice(
                (array) $record,
                $restrictions,
                $userId
            ),
        ]);
    }

    public function restrictionStatus(
        Request $request,
        NoticeRestrictionService $restrictions
    ) {
        $userId = (int) $request->user()->user_id;

        $restrictions->syncExistingNoticesForUser(
            $userId
        );

        return response()->json([
            'success' => true,
            'notice_restriction' =>
                $restrictions->summaryForUser(
                    $userId
                ),
        ]);
    }

    public function respond(
        Request $request,
        $notice,
        NoticeRestrictionService $restrictions
    ) {
        $validated = $request->validate([
            'response_text' => [
                'required',
                'string',
                'max:10000',
            ],
            'evidence' => [
                'nullable',
                'array',
            ],
        ]);

        $userId = (int) $request->user()->user_id;

        $restrictions->syncExistingNoticesForUser(
            $userId
        );

        $restriction =
            LsankNoticeRestriction::query()
                ->where('notice_id', (int) $notice)
                ->where('user_id', $userId)
                ->firstOrFail();

        if ($restriction->notice_type === 'NPP') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Notis kompaun perlu diselesaikan melalui bayaran kompaun.',
            ], 422);
        }

        $updated = $restrictions->markResponded(
            $restriction,
            [
                'response_text' =>
                    trim($validated['response_text']),
                'evidence' =>
                    $validated['evidence'] ?? [],
                'submitted_by' => $userId,
                'submitted_at' =>
                    now()->toDateTimeString(),
            ]
        );

        if (
            Schema::hasTable('lsank_notices') &&
            Schema::hasColumn(
                'lsank_notices',
                'status'
            )
        ) {
            DB::table('lsank_notices')
                ->where(
                    'notice_id',
                    $restriction->notice_id
                )
                ->update([
                    'status' => 'Menunggu Semakan',
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Maklum balas notis berjaya dihantar. Sekatan persona telah dilepaskan.',
            'notice_restriction' =>
                $restrictions->format($updated),
        ]);
    }

    public function acknowledge(
        Request $request,
        $notice,
        NoticeRestrictionService $restrictions
    ) {
        $userId = (int) $request->user()->user_id;

        $restrictions->syncExistingNoticesForUser(
            $userId
        );

        $restriction =
            LsankNoticeRestriction::query()
                ->where('notice_id', (int) $notice)
                ->where('user_id', $userId)
                ->firstOrFail();

        if ($restriction->notice_type !== 'NPK') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pengesahan penerimaan hanya untuk notis NPK.',
            ], 422);
        }

        $updated = $restrictions->markResponded(
            $restriction,
            [
                'acknowledged' => true,
                'submitted_by' => $userId,
                'submitted_at' =>
                    now()->toDateTimeString(),
            ]
        );

        if (
            Schema::hasTable('lsank_notices') &&
            Schema::hasColumn(
                'lsank_notices',
                'status'
            )
        ) {
            DB::table('lsank_notices')
                ->where(
                    'notice_id',
                    $restriction->notice_id
                )
                ->update([
                    'status' => 'Diterima OKN',
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Penerimaan notis telah disahkan. Sekatan persona telah dilepaskan.',
            'notice_restriction' =>
                $restrictions->format($updated),
        ]);
    }

    private function formatNotice(
        array $notice,
        NoticeRestrictionService $restrictions,
        int $userId
    ): array {
        if (
            isset($notice['form_data']) &&
            is_string($notice['form_data'])
        ) {
            $notice['form_data'] =
                json_decode(
                    $notice['form_data'],
                    true
                ) ?: [];
        }

        $restriction =
            LsankNoticeRestriction::query()
                ->where(
                    'notice_id',
                    (int) $notice['notice_id']
                )
                ->where(
                    'user_id',
                    $userId
                )
                ->first();

        $notice['notice_restriction'] =
            $restriction
                ? $restrictions->format(
                    $restriction
                )
                : null;

        return $notice;
    }
}
