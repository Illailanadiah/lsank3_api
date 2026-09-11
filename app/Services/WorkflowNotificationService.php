<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\LsankApplication;
use App\Models\LsankUser;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class WorkflowNotificationService
{
    public function applicationSubmitted(
        LsankApplication $application,
        bool $isRenewal = false
    ): void {
        $module = $this->applicationModule($application);

        $event = $isRenewal
            ? NotificationEvent::APPLICATION_RENEWAL
            : NotificationEvent::APPLICATION_NEW;

        $moduleName = $module === 'effluent'
            ? 'Aktiviti Pelepasan Efluen'
            : 'Aktiviti Badan Perairan';

        $title = $isRenewal
            ? "Permohonan Pembaharuan Baharu – {$moduleName}"
            : "Permohonan Baharu – {$moduleName}";

        $referenceNo = $application->application_ref_no
            ?? 'Permohonan #' . $application->application_id;

        // Alert kumpulan pegawai berkaitan.
        $this->send(
            recipients: $this->teamRecipients($module),
            event: $event,
            title: $title,
            message:
                "Permohonan {$referenceNo} telah dihantar dan memerlukan tindakan.",
            referenceNo: $referenceNo,
            actionUrl: config('app.frontend_url')
                . "/admin/applications/{$module}/"
                . $application->application_id,
            data: [
                'application_id' => $application->application_id,
                'module' => $module,
                'is_renewal' => $isRenewal,
            ],
        );

        // Confirmation kepada pemohon.
        if ($application->user !== null) {
            $this->send(
                recipients: collect([$application->user]),
                event: $event,
                title: 'Permohonan Berjaya Dihantar',
                message:
                    "Permohonan {$referenceNo} telah diterima dan akan diproses.",
                referenceNo: $referenceNo,
                actionUrl: config('app.frontend_url')
                    . "/applications/"
                    . $application->application_id,
                data: [
                    'application_id' => $application->application_id,
                    'module' => $module,
                ],
            );
        }
    }

    public function applicationStatusChanged(
        LsankApplication $application,
        string $oldStatus,
        string $newStatus
    ): void {
        $module = $this->applicationModule($application);
        $referenceNo = $application->application_ref_no
            ?? 'Permohonan #' . $application->application_id;

        // Status kepada user.
        if ($application->user !== null) {
            $this->send(
                recipients: collect([$application->user]),
                event: NotificationEvent::APPLICATION_STATUS_CHANGED,
                title: 'Status Permohonan Dikemaskini',
                message:
                    "Status permohonan {$referenceNo} berubah daripada "
                    . "{$this->statusLabel($oldStatus)} kepada "
                    . "{$this->statusLabel($newStatus)}.",
                referenceNo: $referenceNo,
                actionUrl: config('app.frontend_url')
                    . "/applications/"
                    . $application->application_id,
                data: [
                    'application_id' => $application->application_id,
                    'module' => $module,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
            );
        }

        // Alert pasukan jika status memerlukan tindakan pegawai.
        if ($this->requiresTeamAction($newStatus)) {
            $this->send(
                recipients: $this->teamRecipients($module),
                event: NotificationEvent::APPLICATION_TASK_ASSIGNED,
                title: 'Tindakan Permohonan Diperlukan',
                message:
                    "Permohonan {$referenceNo} kini berstatus "
                    . "{$this->statusLabel($newStatus)} dan memerlukan tindakan.",
                referenceNo: $referenceNo,
                actionUrl: config('app.frontend_url')
                    . "/admin/applications/{$module}/"
                    . $application->application_id,
                data: [
                    'application_id' => $application->application_id,
                    'module' => $module,
                    'status' => $newStatus,
                ],
            );
        }
    }

    private function send(
        Collection $recipients,
        NotificationEvent $event,
        string $title,
        string $message,
        ?string $referenceNo,
        ?string $actionUrl,
        array $data = [],
    ): void {
        $recipients = $recipients
            ->filter(fn ($user) => filled($user?->email))
            ->unique('user_id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new WorkflowNotification(
                eventCode: $event->value,
                title: $title,
                message: $message,
                referenceNo: $referenceNo,
                actionUrl: $actionUrl,
                data: $data,
            )
        );
    }

    private function teamRecipients(
    string $module
): \Illuminate\Support\Collection {
    $userTypes = $module === 'effluent'
        ? [
            'ketua_unit_efluen',
            'ketua_bahagian_efluen',
            'teknikal_efluen',
        ]
        : [
            'ketua_unit_badan_perairan',
            'ketua_bahagian_badan_perairan',
            'teknikal_badan_perairan',
        ];

    return LsankUser::query()
        ->where('status', 'active')
        ->whereIn('user_type', $userTypes)
        ->whereNotNull('email')
        ->where('email', '!=', '')
        ->get();
}

    private function applicationModule(
        LsankApplication $application
    ): string {
        $signals = strtolower(
            trim(
                ($application->application_type ?? '') . ' '
                . ($application->license_type ?? '')
            )
        );

        return str_contains($signals, 'effluent')
            || str_contains($signals, 'efluen')
            ? 'effluent'
            : 'water';
    }

    private function requiresTeamAction(string $status): bool
    {
        return in_array($status, [
            'fi_pemprosesan',
            'dalam_proses',
            'awaiting_head_assignment',
            'assigned_to_technical',
            'technical_report_submitted',
            'technical_feedback_to_head',
            'submitted_to_director',
            'director_review',
        ], true);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draf' => 'Draf',
            'fi_pemprosesan' => 'Fi Pemprosesan',
            'dalam_proses' => 'Dalam Proses',
            'lulus' => 'Lulus',
            'gagal' => 'Gagal',
            'awaiting_head_assignment' =>
                'Menunggu Tugasan Ketua Unit',
            'assigned_to_technical' =>
                'Ditugaskan kepada Pegawai Teknikal',
            'technical_report_submitted' =>
                'Laporan Teknikal Dihantar',
            'submitted_to_director' =>
                'Dihantar kepada Ketua Pengarah',
            'director_review' =>
                'Semakan Ketua Pengarah',
            'director_approved' =>
                'Diluluskan Ketua Pengarah',
            'director_rejected' =>
                'Ditolak Ketua Pengarah',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}