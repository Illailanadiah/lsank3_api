<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class LegalCaseHolderNotificationService
{
    public function caseCreated(
        string $caseType,
        Model $case
    ): Model {
        $caseType = strtolower(
            trim($caseType)
        );

        $link = $this->resolveHolderLink(
            $case
        );

        $this->persistResolvedLink(
            $caseType,
            $case,
            $link
        );

        $case->refresh();

        $userId = (int) (
            $case->user_id
            ?? $link['user_id']
            ?? 0
        );

        if ($userId > 0) {
            $this->createInAppNotification(
                $userId,
                $caseType,
                $case
            );
        }

        if (
            str_contains(
                strtolower(
                    (string) $case->offence
                ),
                'pengabstrakan'
            )
        ) {
            $this
                ->sendPengabstrakanEmail(
                    $caseType,
                    $case
                );
        }

        return $case;
    }

    private function resolveHolderLink(
        Model $case
    ): array {
        $result = [
            'user_id' =>
                (int) (
                    $case->user_id
                    ?? 0
                ),
            'license_id' =>
                (int) (
                    $case->license_id
                    ?? 0
                ),
            'application_id' =>
                (int) (
                    $case
                        ->application_id
                    ?? 0
                ),
        ];

        if (
            $result['user_id'] > 0 &&
            $result['license_id'] > 0
        ) {
            return $result;
        }

        $license = null;

        if ($result['license_id'] > 0) {
            $license =
                DB::table(
                    'lsank_licenses'
                )
                    ->where(
                        'license_id',
                        $result[
                            'license_id'
                        ]
                    )
                    ->first();
        }

        /*
         * Pengabstrakan Air is entered manually in
         * the Criminal form, so opportunistically
         * resolve the real licence using the entered
         * No Lesen / No Fail Lesen.
         */
        if (!$license) {
            $licenseNo =
                trim(
                    (string) (
                        $case->license_no
                        ?? ''
                    )
                );

            $fileNo =
                trim(
                    (string) (
                        $case->file_no
                        ?? ''
                    )
                );

            if (
                $licenseNo !== '' ||
                $fileNo !== ''
            ) {
                $licenseQuery =
                    DB::table(
                        'lsank_licenses'
                    );

                $licenseQuery->where(
                    function ($query) use (
                        $licenseNo,
                        $fileNo
                    ) {
                        if (
                            $licenseNo !== ''
                        ) {
                            $query->where(
                                'license_no',
                                $licenseNo
                            );
                        }

                        if ($fileNo !== '') {
                            if (
                                $licenseNo !== ''
                            ) {
                                $query->orWhere(
                                    'file_no',
                                    $fileNo
                                );
                            } else {
                                $query->where(
                                    'file_no',
                                    $fileNo
                                );
                            }
                        }
                    }
                );

                $license =
                    $licenseQuery
                        ->first();
            }
        }

        if (!$license) {
            return $result;
        }

        $result['license_id'] =
            (int) (
                $license->license_id
                ?? 0
            );

        $result['application_id'] =
            (int) (
                $license->application_id
                ?? $result[
                    'application_id'
                ]
            );

        if (
            $result['application_id'] > 0
        ) {
            $userId =
                (int) DB::table(
                    'lsank_applications'
                )
                    ->where(
                        'application_id',
                        $result[
                            'application_id'
                        ]
                    )
                    ->value('user_id');

            if ($userId > 0) {
                $result['user_id'] =
                    $userId;
            }
        }

        return $result;
    }

    private function persistResolvedLink(
        string $caseType,
        Model $case,
        array $link
    ): void {
        $table = $caseType === 'civil'
            ? 'lsank_civil_cases'
            : 'lsank_criminal_cases';

        $primaryKey = $caseType === 'civil'
            ? 'civil_case_id'
            : 'criminal_case_id';

        $id = (int) (
            $case->{$primaryKey}
            ?? 0
        );

        if (
            $id <= 0 ||
            !Schema::hasTable($table)
        ) {
            return;
        }

        $update = [];

        foreach (
            [
                'user_id',
                'license_id',
                'application_id',
            ] as $column
        ) {
            if (
                Schema::hasColumn(
                    $table,
                    $column
                ) &&
                (
                    (int) (
                        $link[$column]
                        ?? 0
                    )
                ) > 0
            ) {
                $update[$column] =
                    (int) $link[$column];
            }
        }

        if (!$update) {
            return;
        }

        $update['updated_at'] = now();

        DB::table($table)
            ->where(
                $primaryKey,
                $id
            )
            ->update($update);
    }

    private function createInAppNotification(
        int $userId,
        string $caseType,
        Model $case
    ): void {
        if (
            !Schema::hasTable(
                'lsank_notifications'
            )
        ) {
            return;
        }

        $caseNo =
            trim(
                (string) (
                    $case->case_no
                    ?? ''
                )
            );

        $label =
            $caseType === 'civil'
                ? 'Kes Sivil'
                : 'Kes Jenayah';

        $payload = [
            'user_id' => $userId,
            'title' =>
                "{$label} Didaftarkan",
            'message' =>
                "{$label} {$caseNo} telah didaftarkan dan kini boleh dilihat di Notis Saya.",
            'notification_type' =>
                'in_app',
            'related_module' =>
                $caseType === 'civil'
                    ? 'civil_case'
                    : 'criminal_case',
            'related_id' =>
                $caseType === 'civil'
                    ? $case
                        ->civil_case_id
                    : $case
                        ->criminal_case_id,
            'is_read' => 0,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $safe = collect($payload)
            ->filter(
                fn (
                    $value,
                    $column
                ) =>
                    Schema::hasColumn(
                        'lsank_notifications',
                        (string) $column
                    )
            )
            ->all();

        if (
            isset($safe['user_id']) &&
            count($safe) > 1
        ) {
            DB::table(
                'lsank_notifications'
            )->insert($safe);
        }
    }

    private function sendPengabstrakanEmail(
        string $caseType,
        Model $case
    ): void {
        $to = trim(
            (string) config(
                'legal_notifications.pengabstrakan_email',
                'illailanadiah19@gmail.com'
            )
        );

        if ($to === '') {
            return;
        }

        $label =
            $caseType === 'civil'
                ? 'Kes Sivil'
                : 'Kes Jenayah';

        $body =
            "Notifikasi LSANK - {$label}\n\n"
            . "Kesalahan: Pengabstrakan Air\n"
            . "No Kes: "
            . (
                $case->case_no
                ?? '-'
            )
            . "\n"
            . "Nama Pihak: "
            . (
                $case->party_name
                ?? '-'
            )
            . "\n"
            . "No Fail Lesen: "
            . (
                $case->file_no
                ?? '-'
            )
            . "\n"
            . "No Lesen: "
            . (
                $case->license_no
                ?? $case->license?->license_no
                ?? '-'
            )
            . "\n"
            . "Status: "
            . (
                $case->case_status
                ?? '-'
            )
            . "\n\n"
            . "E-mel ini dihantar ke alamat ujian yang boleh diganti melalui LSANK_PENGABSTRAKAN_LEGAL_EMAIL.";

        Mail::raw(
            $body,
            function ($message) use (
                $to,
                $label,
                $case
            ) {
                $message
                    ->to($to)
                    ->subject(
                        'LSANK - '
                        . $label
                        . ' Pengabstrakan Air - '
                        . (
                            $case->case_no
                            ?? 'Kes Baru'
                        )
                    );
            }
        );
    }
}
