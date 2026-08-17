<?php

namespace App\Observers;

use App\Models\LsankNotice;
use App\Services\NoticeRestrictionService;

class LsankNoticeObserver
{
    public function created(LsankNotice $notice): void
    {
        $formData = is_array($notice->form_data)
            ? $notice->form_data
            : [];

        app(NoticeRestrictionService::class)
            ->activateForNotice(
                (int) $notice->notice_id,
                $notice->notice_no,
                (string) $notice->notice_type,
                $notice->category,
                $formData,
                $notice->created_by
                    ? (int) $notice->created_by
                    : null,
            );
    }

    public function updated(LsankNotice $notice): void
    {
        if (!$notice->wasChanged('status')) {
            return;
        }

        $status = strtolower(
            trim((string) $notice->status)
        );

        $closed = [
            'batal',
            'cancelled',
            'canceled',
            'closed',
            'ditutup',
            'selesai',
            'completed',
            'tamat',
        ];

        if (in_array($status, $closed, true)) {
            app(NoticeRestrictionService::class)
                ->cancelByNoticeId(
                    (int) $notice->notice_id
                );
        }
    }
}
