<?php

namespace App\Console\Commands;

use App\Services\NoticeRestrictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncNoticeRestrictions extends Command
{
    protected $signature = 'lsank:notices-sync';

    protected $description =
        'Backfill notice restrictions for notices created before the notice restriction module.';

    public function handle(
        NoticeRestrictionService $service
    ): int {
        if (
            !Schema::hasTable('lsank_applications') ||
            !Schema::hasColumn(
                'lsank_applications',
                'user_id'
            )
        ) {
            $this->error(
                'lsank_applications.user_id not found.'
            );

            return self::FAILURE;
        }

        $userIds = DB::table('lsank_applications')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter();

        $total = 0;

        foreach ($userIds as $userId) {
            $created =
                $service->syncExistingNoticesForUser(
                    $userId
                );

            if ($created > 0) {
                $this->line(
                    "user_id={$userId}: {$created} notice(s) linked."
                );
            }

            $total += $created;
        }

        $this->info(
            "Done. {$total} existing notice restriction(s) created."
        );

        return self::SUCCESS;
    }
}
