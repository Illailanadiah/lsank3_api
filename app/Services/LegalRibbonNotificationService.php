<?php

namespace App\Services;

use App\Models\LsankCivilCase;
use App\Models\LsankNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegalRibbonNotificationService
{
    /**
     * Create all in-app notifications after a Civil Case is created.
     *
     * Water Body / Effluent:
     * - normally comes from Penguatkuasa / legal referral.
     *
     * Pengabstrakan Air:
     * - manually created by Perundangan.
     */
    public function civilCreated(
        LsankCivilCase $case,
        ?int $actorUserId = null
    ): void {
        $caseId = (int) $case->getKey();

        if ($caseId <= 0) {
            return;
        }

        $actorUserId = $actorUserId
            ?: $this->integerOrNull($case->created_by ?? null);

        $offence = trim(
            (string) ($case->offence ?? '')
        );

        $caseNo = trim(
            (string) ($case->case_no ?? '')
        );

        $partyName = trim(
            (string) ($case->party_name ?? '')
        );

        $licenseNo = $this->resolveLicenseNo($case);
        $fileNo = $this->resolveFileNo($case);
        $holderUserId = $this->resolveHolderUserId($case);

        $isPengabstrakan = $this->contains(
            $offence,
            'pengabstrakan'
        );

        $isWaterBody =
            $this->contains($offence, 'badan perairan')
            || $this->contains($offence, 'water');

        $isEffluent =
            $this->contains($offence, 'efluen')
            || $this->contains($offence, 'effluent');

        $metadata = [
            'case_type' => 'civil',
            'civil_case_id' => $caseId,
            'case_no' => $caseNo,
            'offence' => $offence,
            'party_name' => $partyName,
            'license_no' => $licenseNo,
            'file_no' => $fileNo,
            'legal_referral_id' =>
                $case->legal_referral_id ?? null,
            'notice_id' => $case->notice_id ?? null,
            'application_id' => $case->application_id ?? null,
            'license_id' => $case->license_id ?? null,
            'manual_pengabstrakan' => $isPengabstrakan,
        ];

        /*
        |--------------------------------------------------------------------------
        | 1. License holder
        |--------------------------------------------------------------------------
        */
        if ($holderUserId !== null) {
            $holderTitle = $isPengabstrakan
                ? 'Tindakan Perundangan - Pengabstrakan Air'
                : 'Tindakan Perundangan Sivil';

            $holderMessage = $isPengabstrakan
                ? $this->compactMessage([
                    'Satu kes sivil Pengabstrakan Air telah didaftarkan oleh Unit Perundangan.',
                    $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                    $licenseNo !== '' ? "No. Lesen: {$licenseNo}." : null,
                    'Sila semak maklumat kes dan tindakan yang diperlukan.',
                ])
                : $this->compactMessage([
                    'Tindakan Perundangan Sivil telah dimulakan bagi lesen anda.',
                    $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                    $offence !== '' ? "Aktiviti: {$offence}." : null,
                    $licenseNo !== '' ? "No. Lesen: {$licenseNo}." : null,
                    'Sila semak maklumat dan tindakan yang diperlukan.',
                ]);

            $this->createOrUpdate(
                userId: $holderUserId,
                eventKey:
                    "civil_created:{$caseId}:holder:{$holderUserId}",
                values: [
                    'title' => $holderTitle,
                    'message' => $holderMessage,
                    'event_type' => 'legal_civil_created',
                    'audience' => 'license_holder',
                    'severity' => 'danger',
                    'priority' => 1,
                    'related_module' => 'civil_case',
                    'related_id' => $caseId,
                    'action_required' => true,
                    'action_label' => 'Semak Kes',
                    'action_url' =>
                        "/notices/detail?source=civil&caseId={$caseId}",
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 7,
                    'metadata' => $metadata,
                ],
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Perundangan
        |--------------------------------------------------------------------------
        */
        foreach ($this->legalUserIds() as $legalUserId) {
            $legalMessage = $isPengabstrakan
                ? $this->compactMessage([
                    'Kes Sivil Pengabstrakan Air telah diwujudkan secara manual.',
                    $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                    $partyName !== '' ? "Pihak: {$partyName}." : null,
                    'Semakan dan tindakan susulan Perundangan diperlukan.',
                ])
                : $this->compactMessage([
                    'Kes Sivil baharu daripada aliran Penguatkuasa memerlukan semakan.',
                    $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                    $partyName !== '' ? "Pihak: {$partyName}." : null,
                    $offence !== '' ? "Aktiviti: {$offence}." : null,
                ]);

            $this->createOrUpdate(
                userId: $legalUserId,
                eventKey:
                    "civil_created:{$caseId}:legal:{$legalUserId}",
                values: [
                    'title' => $isPengabstrakan
                        ? 'Kes Manual Pengabstrakan Air'
                        : 'Kes Sivil Baharu - Tindakan Diperlukan',
                    'message' => $legalMessage,
                    'event_type' => 'legal_civil_action_required',
                    'audience' => 'perundangan',
                    'severity' => 'warning',
                    'priority' => 1,
                    'related_module' => 'civil_case',
                    'related_id' => $caseId,
                    'action_required' => true,
                    'action_label' => 'Semak Kes Sivil',
                    'action_url' => '/admin/legal/civil',
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 7,
                    'metadata' => $metadata,
                ],
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Penguatkuasa who initiated/created the referral/case
        |--------------------------------------------------------------------------
        |
        | Pengabstrakan Air is manual Perundangan, so no Penguatkuasa
        | acknowledgement is generated from this Civil creation.
        |
        */
        if (
            !$isPengabstrakan
            && $actorUserId !== null
        ) {
            $this->createOrUpdate(
                userId: $actorUserId,
                eventKey:
                    "civil_created:{$caseId}:enforcement:{$actorUserId}",
                values: [
                    'title' => 'Rujukan Perundangan Direkodkan',
                    'message' => $this->compactMessage([
                        'Kes Sivil bagi rujukan Penguatkuasa telah direkodkan.',
                        $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                        $partyName !== '' ? "Pihak: {$partyName}." : null,
                        'Unit Perundangan akan meneruskan tindakan seterusnya.',
                    ]),
                    'event_type' => 'legal_referral_processed',
                    'audience' => 'penguatkuasa',
                    'severity' => 'info',
                    'priority' => 3,
                    'related_module' => 'civil_case',
                    'related_id' => $caseId,
                    'action_required' => false,
                    'action_label' => 'Lihat Notis',
                    'action_url' => '/admin/notices',
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 6,
                    'metadata' => $metadata,
                ],
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Water Body / Effluent department users
        |--------------------------------------------------------------------------
        |
        | They receive status information only. They do not edit legal records.
        |
        */
        $departmentTokens = [];

        if ($isWaterBody) {
            $departmentTokens = [
                'badan_perairan',
            ];
        } elseif ($isEffluent) {
            $departmentTokens = [
                'efluen',
                'effluent',
            ];
        }

        if ($departmentTokens !== []) {
            foreach (
                $this->userIdsContainingUserTypes(
                    $departmentTokens
                )
                as $departmentUserId
            ) {
                $this->createOrUpdate(
                    userId: $departmentUserId,
                    eventKey:
                        "civil_created:{$caseId}:department:{$departmentUserId}",
                    values: [
                        'title' => 'Status Perundangan Aktif',
                        'message' => $this->compactMessage([
                            'Pemegang lesen berkaitan kini mempunyai tindakan Perundangan Sivil aktif.',
                            $partyName !== '' ? "Pihak: {$partyName}." : null,
                            $licenseNo !== '' ? "No. Lesen: {$licenseNo}." : null,
                            'Pemprosesan baharu/renewal hendaklah mematuhi sekatan Perundangan yang aktif.',
                        ]),
                        'event_type' => 'legal_civil_status_notice',
                        'audience' => 'department',
                        'severity' => 'danger',
                        'priority' => 2,
                        'related_module' => 'civil_case',
                        'related_id' => $caseId,
                        'action_required' => false,
                        'action_label' => 'Lihat Lesen',
                        'action_url' => '/admin/licenses',
                        'show_as_ribbon' => true,
                        'ribbon_duration_seconds' => 7,
                        'metadata' => $metadata,
                    ],
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Management / admin visibility
        |--------------------------------------------------------------------------
        */
        foreach (
            $this->userIdsMatchingUserTypes([
                'admin',
                'ketua_pengarah',
            ])
            as $managementUserId
        ) {
            $this->createOrUpdate(
                userId: $managementUserId,
                eventKey:
                    "civil_created:{$caseId}:management:{$managementUserId}",
                values: [
                    'title' => 'Makluman Kes Perundangan Sivil',
                    'message' => $this->compactMessage([
                        'Satu kes Perundangan Sivil aktif telah direkodkan.',
                        $caseNo !== '' ? "No. Kes: {$caseNo}." : null,
                        $partyName !== '' ? "Pihak: {$partyName}." : null,
                        $offence !== '' ? "Aktiviti: {$offence}." : null,
                    ]),
                    'event_type' => 'legal_civil_management_notice',
                    'audience' => 'management',
                    'severity' => 'info',
                    'priority' => 3,
                    'related_module' => 'civil_case',
                    'related_id' => $caseId,
                    'action_required' => false,
                    'action_label' => 'Lihat Perundangan',
                    'action_url' => '/admin/legal/civil',
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 6,
                    'metadata' => $metadata,
                ],
            );
        }
    }

    /**
     * Optional helper for the moment a legal referral is created,
     * before a Civil Case exists.
     *
     * Call this from the Penguatkuasa / escalation flow if required.
     */
    public function legalReferralCreated(
        object $referral,
        ?int $actorUserId = null
    ): void {
        $referralId = $this->integerOrNull(
            $referral->legal_referral_id
                ?? $referral->id
                ?? null
        );

        if ($referralId === null) {
            return;
        }

        $noticeNo = trim(
            (string) ($referral->notice_no ?? '')
        );

        $partyName = trim(
            (string) ($referral->party_name ?? '')
        );

        foreach ($this->legalUserIds() as $legalUserId) {
            $this->createOrUpdate(
                userId: $legalUserId,
                eventKey:
                    "legal_referral:{$referralId}:legal:{$legalUserId}",
                values: [
                    'title' => 'Rujukan Baharu Daripada Penguatkuasa',
                    'message' => $this->compactMessage([
                        'Satu rujukan baharu memerlukan tindakan Unit Perundangan.',
                        $noticeNo !== '' ? "Notis: {$noticeNo}." : null,
                        $partyName !== '' ? "Pihak: {$partyName}." : null,
                    ]),
                    'event_type' => 'legal_referral_created',
                    'audience' => 'perundangan',
                    'severity' => 'warning',
                    'priority' => 1,
                    'related_module' => 'legal_referral',
                    'related_id' => $referralId,
                    'action_required' => true,
                    'action_label' => 'Semak Rujukan',
                    'action_url' => '/admin/legal',
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 7,
                    'metadata' => [
                        'legal_referral_id' => $referralId,
                        'notice_no' => $noticeNo,
                        'party_name' => $partyName,
                    ],
                ],
            );
        }

        if ($actorUserId !== null) {
            $this->createOrUpdate(
                userId: $actorUserId,
                eventKey:
                    "legal_referral:{$referralId}:enforcement:{$actorUserId}",
                values: [
                    'title' => 'Rujukan Dihantar ke Perundangan',
                    'message' =>
                        'Rujukan telah berjaya dihantar kepada Unit Perundangan.',
                    'event_type' => 'legal_referral_sent',
                    'audience' => 'penguatkuasa',
                    'severity' => 'success',
                    'priority' => 3,
                    'related_module' => 'legal_referral',
                    'related_id' => $referralId,
                    'action_required' => false,
                    'action_label' => 'Lihat Notis',
                    'action_url' => '/admin/notices',
                    'show_as_ribbon' => true,
                    'ribbon_duration_seconds' => 5,
                    'metadata' => [
                        'legal_referral_id' => $referralId,
                        'notice_no' => $noticeNo,
                        'party_name' => $partyName,
                    ],
                ],
            );
        }
    }

    private function createOrUpdate(
        int $userId,
        string $eventKey,
        array $values
    ): void {
        if ($userId <= 0) {
            return;
        }

        LsankNotification::query()->updateOrCreate(
            [
                'event_key' => $eventKey,
            ],
            array_merge(
                [
                    'user_id' => $userId,

                    // Existing column is the delivery channel.
                    'notification_type' => 'in_app',

                    'event_type' => 'general',
                    'audience' => null,
                    'severity' => 'info',
                    'priority' => 3,

                    'title' => 'Makluman LSANK',
                    'message' => '',
                    'related_module' => null,
                    'related_id' => null,

                    'action_required' => false,
                    'action_label' => null,
                    'action_url' => null,

                    'show_as_ribbon' => false,
                    'ribbon_duration_seconds' => 7,

                    'is_read' => false,
                    'read_at' => null,
                    'dismissed_at' => null,
                    'action_completed_at' => null,
                    'expires_at' => null,

                    'metadata' => null,
                    'sent_at' => now(),
                ],
                $values
            )
        );
    }

    private function resolveHolderUserId(
        LsankCivilCase $case
    ): ?int {
        $direct = $this->integerOrNull(
            $case->user_id ?? null
        );

        if ($direct !== null) {
            return $direct;
        }

        $applicationId = $this->integerOrNull(
            $case->application_id ?? null
        );

        if (
            $applicationId !== null
            && Schema::hasTable('lsank_applications')
            && Schema::hasColumn(
                'lsank_applications',
                'user_id'
            )
        ) {
            $value = DB::table('lsank_applications')
                ->where('application_id', $applicationId)
                ->value('user_id');

            $resolved = $this->integerOrNull($value);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $licenseId = $this->integerOrNull(
            $case->license_id ?? null
        );

        if (
            $licenseId !== null
            && Schema::hasTable('lsank_licenses')
        ) {
            $license = DB::table('lsank_licenses')
                ->where('license_id', $licenseId)
                ->first();

            if ($license) {
                $directUser = $this->integerOrNull(
                    $license->user_id ?? null
                );

                if ($directUser !== null) {
                    return $directUser;
                }

                $licenseApplicationId =
                    $this->integerOrNull(
                        $license->application_id
                            ?? null
                    );

                if (
                    $licenseApplicationId !== null
                    && Schema::hasTable(
                        'lsank_applications'
                    )
                ) {
                    $value = DB::table(
                        'lsank_applications'
                    )
                        ->where(
                            'application_id',
                            $licenseApplicationId
                        )
                        ->value('user_id');

                    return $this->integerOrNull(
                        $value
                    );
                }
            }
        }

        return null;
    }

    private function resolveLicenseNo(
        LsankCivilCase $case
    ): string {
        $direct = trim(
            (string) ($case->license_no ?? '')
        );

        if ($direct !== '') {
            return $direct;
        }

        $license = $this->licenseRecord($case);

        return trim(
            (string) ($license->license_no ?? '')
        );
    }

    private function resolveFileNo(
        LsankCivilCase $case
    ): string {
        $direct = trim(
            (string) ($case->file_no ?? '')
        );

        if ($direct !== '') {
            return $direct;
        }

        $license = $this->licenseRecord($case);

        return trim(
            (string) ($license->file_no ?? '')
        );
    }

    private function licenseRecord(
        LsankCivilCase $case
    ): ?object {
        $licenseId = $this->integerOrNull(
            $case->license_id ?? null
        );

        if (
            $licenseId === null
            || !Schema::hasTable('lsank_licenses')
        ) {
            return null;
        }

        return DB::table('lsank_licenses')
            ->where('license_id', $licenseId)
            ->first();
    }

    private function legalUserIds(): array
    {
        return $this->userIdsContainingUserTypes([
            'perundangan',
            'undang',
            'legal',
        ]);
    }

    private function userIdsContainingUserTypes(
        array $tokens
    ): array {
        $table = $this->userTable();

        if ($table === null) {
            return [];
        }

        $idColumn = $this->userIdColumn($table);

        if ($idColumn === null) {
            return [];
        }

        $query = DB::table($table)
            ->where('status', 'active')
            ->where(function ($query) use ($tokens) {
                foreach ($tokens as $index => $token) {
                    $token = strtolower(
                        trim((string) $token)
                    );

                    if ($token === '') {
                        continue;
                    }

                    if ($index === 0) {
                        $query->whereRaw(
                            'LOWER(user_type) LIKE ?',
                            ["%{$token}%"]
                        );
                    } else {
                        $query->orWhereRaw(
                            'LOWER(user_type) LIKE ?',
                            ["%{$token}%"]
                        );
                    }
                }
            });

        return $query
            ->pluck($idColumn)
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn ($id) => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    private function userIdsMatchingUserTypes(
        array $types
    ): array {
        $table = $this->userTable();

        if ($table === null) {
            return [];
        }

        $idColumn = $this->userIdColumn($table);

        if ($idColumn === null) {
            return [];
        }

        $types = collect($types)
            ->map(
                fn ($value) => strtolower(
                    trim((string) $value)
                )
            )
            ->filter()
            ->values()
            ->all();

        if ($types === []) {
            return [];
        }

        return DB::table($table)
            ->where('status', 'active')
            ->whereIn(
                DB::raw('LOWER(user_type)'),
                $types
            )
            ->pluck($idColumn)
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn ($id) => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Detect the populated LSANK user table.
     *
     * This avoids depending on the default Laravel users table when
     * the project authenticates against a separate LSANK user table.
     */
    private function userTable(): ?string
    {
        $candidates = [
            'lsank_users',
            'users',
        ];

        foreach ($candidates as $table) {
            if (
                !Schema::hasTable($table)
                || !Schema::hasColumn(
                    $table,
                    'user_type'
                )
            ) {
                continue;
            }

            if (
                DB::table($table)->limit(1)->exists()
            ) {
                return $table;
            }
        }

        foreach ($candidates as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn(
                    $table,
                    'user_type'
                )
            ) {
                return $table;
            }
        }

        return null;
    }

    private function userIdColumn(
        string $table
    ): ?string {
        if (
            Schema::hasColumn(
                $table,
                'user_id'
            )
        ) {
            return 'user_id';
        }

        if (
            Schema::hasColumn(
                $table,
                'id'
            )
        ) {
            return 'id';
        }

        return null;
    }

    private function integerOrNull(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }

    private function contains(
        string $value,
        string $needle
    ): bool {
        return str_contains(
            strtolower($value),
            strtolower($needle)
        );
    }

    private function compactMessage(
        array $parts
    ): string {
        return collect($parts)
            ->filter(
                fn ($part) =>
                    $part !== null
                    && trim((string) $part) !== ''
            )
            ->map(
                fn ($part) =>
                    trim((string) $part)
            )
            ->implode(' ');
    }
}
