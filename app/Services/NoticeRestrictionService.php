<?php

namespace App\Services;

use App\Models\LsankApplication;
use App\Models\LsankNoticeRestriction;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NoticeRestrictionService
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CANCELLED = 'cancelled';

    public function activeForUser(?int $userId): Collection
    {
        if (!$userId) {
            return collect();
        }

        return LsankNoticeRestriction::query()
            ->where('user_id', $userId)
            ->where('restriction_status', self::STATUS_ACTIVE)
            ->latest('restriction_id')
            ->get();
    }

    public function isRestricted(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return LsankNoticeRestriction::query()
            ->where('user_id', $userId)
            ->where('restriction_status', self::STATUS_ACTIVE)
            ->exists();
    }

    public function summaryForUser(?int $userId): array
    {
        $active = $this->activeForUser($userId);

        return [
            'active' => $active->isNotEmpty(),
            'count' => $active->count(),
            'message' => $active->isEmpty()
                ? null
                : 'Persona mempunyai notis aktif. Permohonan baharu, pembaharuan dan tindakan pemprosesan disekat sehingga tindakan notis diselesaikan.',
            'notices' => $active
                ->map(fn (LsankNoticeRestriction $item) => $this->format($item))
                ->values()
                ->all(),
        ];
    }

    public function assertUserMayApply(?int $userId): void
    {
        // Important: this also picks up notices created before the restriction
        // module was installed.
        if ($userId) {
            $this->syncExistingNoticesForUser($userId);
        }

        $summary = $this->summaryForUser($userId);

        if (!$summary['active']) {
            return;
        }

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'restricted' => true,
                'code' => 'NOTICE_RESTRICTION_ACTIVE',
                'message' => $summary['message'],
                'notice_restriction' => $summary,
            ], 423)
        );
    }

    public function assertApplicationMayProceed(
        LsankApplication $application
    ): void {
        $this->assertUserMayApply(
            (int) $application->user_id
        );
    }

    /**
     * Backfill notices that already existed before the restriction feature.
     *
     * The user notice endpoint previously INNER JOINed only
     * lsank_notice_restrictions. Therefore an older admin notice could exist
     * in lsank_notices while "Notis Saya" was empty. This method links those
     * existing notices to the real persona using license_id/application_id
     * stored inside form_data.
     */
    public function syncExistingNoticesForUser(int $userId): int
    {
        if (
            $userId <= 0 ||
            !Schema::hasTable('lsank_notices') ||
            !Schema::hasTable('lsank_notice_restrictions')
        ) {
            return 0;
        }

        $userApplicationIds = collect();

        if (
            Schema::hasTable('lsank_applications') &&
            Schema::hasColumn('lsank_applications', 'user_id')
        ) {
            $userApplicationIds = DB::table('lsank_applications')
                ->where('user_id', $userId)
                ->pluck('application_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
        }

        $userLicenseIds = collect();

        if (Schema::hasTable('lsank_licenses')) {
            $licenseQuery = DB::table('lsank_licenses');

            $licenseQuery->where(function ($query) use (
                $userId,
                $userApplicationIds
            ) {
                $hasCondition = false;

                if (Schema::hasColumn('lsank_licenses', 'user_id')) {
                    $query->where('user_id', $userId);
                    $hasCondition = true;
                }

                if (
                    Schema::hasColumn('lsank_licenses', 'application_id') &&
                    $userApplicationIds->isNotEmpty()
                ) {
                    if ($hasCondition) {
                        $query->orWhereIn(
                            'application_id',
                            $userApplicationIds->all()
                        );
                    } else {
                        $query->whereIn(
                            'application_id',
                            $userApplicationIds->all()
                        );
                    }
                }
            });

            $userLicenseIds = $licenseQuery
                ->pluck('license_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
        }

        if (
            $userLicenseIds->isEmpty() &&
            $userApplicationIds->isEmpty()
        ) {
            return 0;
        }

        $existingNoticeIds = LsankNoticeRestriction::query()
            ->pluck('notice_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = DB::table('lsank_notices')
            ->orderBy('notice_id');

        if (!empty($existingNoticeIds)) {
            $query->whereNotIn('notice_id', $existingNoticeIds);
        }

        $rows = $query->get();

        $created = 0;

        foreach ($rows as $row) {
            $notice = (array) $row;

            if (!$this->noticeStatusCanRestrict(
                $notice['status'] ?? null
            )) {
                continue;
            }

            $formData = $this->decodeFormData(
                $notice['form_data'] ?? []
            );

            $licenseId = $this->findNumericValue(
                $formData,
                ['license_id']
            );

            $applicationId = $this->findNumericValue(
                $formData,
                ['application_id']
            );

            $linkedUserId = $this->findNumericValue(
                $formData,
                ['user_id']
            );

            $belongsToUser =
                ($linkedUserId === $userId) ||
                (
                    $licenseId &&
                    $userLicenseIds->contains($licenseId)
                ) ||
                (
                    $applicationId &&
                    $userApplicationIds->contains($applicationId)
                );

            if (!$belongsToUser) {
                continue;
            }

            // Make the current user explicit for old form_data that only kept
            // license_id. activateForNotice still resolves from DB as well.
            $formData['_restriction_user_id'] = $userId;

            $restriction = $this->activateForNotice(
                (int) $notice['notice_id'],
                isset($notice['notice_no'])
                    ? (string) $notice['notice_no']
                    : null,
                isset($notice['notice_type'])
                    ? (string) $notice['notice_type']
                    : 'NPK',
                isset($notice['category'])
                    ? (string) $notice['category']
                    : null,
                $formData,
                isset($notice['created_by'])
                    ? (int) $notice['created_by']
                    : null,
                $userId,
            );

            if ($restriction) {
                $created++;
            }
        }

        return $created;
    }

    public function activateForNotice(
        int $noticeId,
        ?string $noticeNo,
        string $noticeType,
        ?string $category,
        array $formData,
        ?int $createdBy = null,
        ?int $forcedUserId = null
    ): ?LsankNoticeRestriction {
        $licenseId = $this->findNumericValue(
            $formData,
            ['license_id']
        );

        $applicationId = $this->findNumericValue(
            $formData,
            ['application_id']
        );

        $userId = $forcedUserId
            ?: $this->findNumericValue(
                $formData,
                ['user_id', '_restriction_user_id']
            );

        if ($licenseId) {
            $license = DB::table('lsank_licenses')
                ->where('license_id', $licenseId)
                ->first();

            if ($license) {
                $applicationId = $applicationId
                    ?: (int) ($license->application_id ?? 0)
                    ?: null;

                if (
                    !$userId &&
                    Schema::hasColumn('lsank_licenses', 'user_id')
                ) {
                    $userId = (int) ($license->user_id ?? 0)
                        ?: null;
                }
            }
        }

        if (!$userId && $applicationId) {
            $application = DB::table('lsank_applications')
                ->where('application_id', $applicationId)
                ->first();

            $userId = (int) ($application->user_id ?? 0)
                ?: null;
        }

        if (!$userId) {
            return null;
        }

        $type = strtoupper(trim($noticeType));

        $responseRequired = match ($type) {
            'NPP' => 'compound_payment',
            'N70' => 'n70_response',
            default => 'acknowledge',
        };

        $restriction = LsankNoticeRestriction::query()
            ->where('notice_id', $noticeId)
            ->first();

        $isNew = $restriction === null;

        if (!$restriction) {
            $restriction = new LsankNoticeRestriction();
            $restriction->notice_id = $noticeId;
        }

        $restriction->fill([
            'user_id' => $userId,
            'license_id' => $licenseId,
            'application_id' => $applicationId,
            'notice_no' => $noticeNo,
            'notice_type' => $type,
            'category' => $category
                ? strtoupper(trim($category))
                : null,
            'restriction_status' => self::STATUS_ACTIVE,
            'response_required' => $responseRequired,
            'restriction_reason' =>
                'Sekatan automatik kerana notis penguatkuasaan aktif.',
            'created_by' => $createdBy,
        ]);

        if ($isNew) {
            $restriction->response_data = null;
            $restriction->responded_at = null;
            $restriction->resolved_at = null;
        }

        $restriction->save();

        // Prevent duplicate bell notifications when user list is refreshed.
        if ($isNew) {
            $this->createUserNotification($restriction);
        }

        return $restriction->fresh();
    }

    public function markResponded(
        LsankNoticeRestriction $restriction,
        array $responseData
    ): LsankNoticeRestriction {
        if ($restriction->response_required === 'compound_payment') {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' =>
                        'Notis kompaun hanya boleh dilepaskan selepas bayaran kompaun berjaya.',
                ], 422)
            );
        }

        $restriction->forceFill([
            'restriction_status' => self::STATUS_RESPONDED,
            'response_data' => $responseData,
            'responded_at' => now(),
            'resolved_at' => now(),
        ])->save();

        return $restriction->fresh();
    }

    public function resolve(
        LsankNoticeRestriction $restriction,
        string $reason = 'resolved'
    ): LsankNoticeRestriction {
        $responseData = is_array($restriction->response_data)
            ? $restriction->response_data
            : [];

        $responseData['resolution_reason'] = $reason;

        $restriction->forceFill([
            'restriction_status' => self::STATUS_RESOLVED,
            'response_data' => $responseData,
            'resolved_at' => now(),
        ])->save();

        return $restriction->fresh();
    }

    public function resolveByNoticeId(
        int $noticeId,
        string $reason = 'resolved'
    ): ?LsankNoticeRestriction {
        $restriction = LsankNoticeRestriction::query()
            ->where('notice_id', $noticeId)
            ->first();

        return $restriction
            ? $this->resolve($restriction, $reason)
            : null;
    }

    public function resolveCompoundPaid(
        int $noticeId,
        array $paymentData = []
    ): ?LsankNoticeRestriction {
        $restriction = LsankNoticeRestriction::query()
            ->where('notice_id', $noticeId)
            ->where('notice_type', 'NPP')
            ->first();

        if (!$restriction) {
            return null;
        }

        $responseData = is_array($restriction->response_data)
            ? $restriction->response_data
            : [];

        $responseData['compound_payment'] = $paymentData;
        $responseData['resolution_reason'] = 'compound_paid';

        $restriction->forceFill([
            'restriction_status' => self::STATUS_RESOLVED,
            'response_data' => $responseData,
            'responded_at' => now(),
            'resolved_at' => now(),
        ])->save();

        return $restriction->fresh();
    }

    public function cancelByNoticeId(int $noticeId): void
    {
        LsankNoticeRestriction::query()
            ->where('notice_id', $noticeId)
            ->where('restriction_status', self::STATUS_ACTIVE)
            ->update([
                'restriction_status' => self::STATUS_CANCELLED,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function format(
        LsankNoticeRestriction $restriction
    ): array {
        return [
            'restriction_id' => $restriction->restriction_id,
            'notice_id' => $restriction->notice_id,
            'notice_no' => $restriction->notice_no,
            'notice_type' => $restriction->notice_type,
            'category' => $restriction->category,
            'user_id' => $restriction->user_id,
            'license_id' => $restriction->license_id,
            'application_id' => $restriction->application_id,
            'active' =>
                $restriction->restriction_status === self::STATUS_ACTIVE,
            'status' => $restriction->restriction_status,
            'response_required' => $restriction->response_required,
            'restriction_reason' => $restriction->restriction_reason,
            'response_data' => $restriction->response_data,
            'responded_at' => optional($restriction->responded_at)
                ?->toDateTimeString(),
            'resolved_at' => optional($restriction->resolved_at)
                ?->toDateTimeString(),
        ];
    }

    private function createUserNotification(
        LsankNoticeRestriction $restriction
    ): void {
        if (!Schema::hasTable('lsank_notifications')) {
            return;
        }

        $action = match ($restriction->response_required) {
            'compound_payment' =>
                'Sila semak dan bayar kompaun untuk melepaskan sekatan.',
            'n70_response' =>
                'Sila hantar maklum balas / bukti tindakan N70 untuk melepaskan sekatan.',
            default =>
                'Sila buka notis dan sahkan penerimaan.',
        };

        $payload = [
            'user_id' => $restriction->user_id,
            'title' =>
                'Notis Penguatkuasaan '
                . ($restriction->notice_no ?: ''),
            'message' =>
                'Notis '
                . $restriction->notice_type
                . ' telah dikeluarkan kepada anda. '
                . $action,
            'notification_type' => 'in_app',
            'related_module' => 'notice',
            'related_id' => $restriction->notice_id,
            'is_read' => 0,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Keep only columns that really exist in the current notification
        // schema so notification creation cannot break notice creation.
        $safePayload = collect($payload)
            ->filter(
                fn ($value, $column) =>
                    Schema::hasColumn(
                        'lsank_notifications',
                        (string) $column
                    )
            )
            ->all();

        if (
            isset($safePayload['user_id']) &&
            count($safePayload) > 1
        ) {
            DB::table('lsank_notifications')
                ->insert($safePayload);
        }
    }

    private function noticeStatusCanRestrict(mixed $status): bool
    {
        $value = strtolower(trim((string) $status));

        if ($value === '') {
            return true;
        }

        foreach ([
            'batal',
            'cancelled',
            'canceled',
            'closed',
            'ditutup',
            'selesai',
            'completed',
            'tamat',
        ] as $closed) {
            if (str_contains($value, $closed)) {
                return false;
            }
        }

        return true;
    }

    private function decodeFormData(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }

    private function findNumericValue(
        mixed $value,
        array $keys
    ): ?int {
        if (!is_array($value)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) {
                $candidate = (int) $value[$key];

                if ($candidate > 0) {
                    return $candidate;
                }
            }
        }

        foreach ($value as $nested) {
            if (!is_array($nested)) {
                continue;
            }

            $found = $this->findNumericValue(
                $nested,
                $keys
            );

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
