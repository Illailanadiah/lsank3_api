<?php

namespace App\Console\Commands;

use App\Models\LsankApplication;
use Illuminate\Console\Command;

class SyncOldDraftReferenceNo extends Command
{
    protected $signature = 'lsank:sync-old-draft-ref-no';

    protected $description = 'Tukar application_ref_no draf lama kepada format baru DRAF-{user_id}-{YmdHis}';

    public function handle(): int
    {
        $drafts = LsankApplication::query()
            ->where('application_status', LsankApplication::STATUS_DRAF)
            ->where(function ($query) {
                $query->whereNull('application_ref_no')
                    ->orWhere('application_ref_no', '')
                    ->orWhere('application_ref_no', 'not like', 'DRAF-%');
            })
            ->orderBy('application_id')
            ->get();

        $updated = 0;

        foreach ($drafts as $draft) {
            $createdAt = $draft->created_at ?? now();

            $baseRefNo = 'DRAF-' .
                $draft->user_id .
                '-' .
                $createdAt->format('YmdHis');

            $refNo = $baseRefNo;
            $counter = 1;

            while (
                LsankApplication::where('application_ref_no', $refNo)
                    ->where('application_id', '!=', $draft->application_id)
                    ->exists()
            ) {
                $refNo = $baseRefNo . '-' . $counter;
                $counter++;
            }

            $draft->application_ref_no = $refNo;
            $draft->save();

            $updated++;
        }

        $this->info("Selesai tukar {$updated} nombor draf lama kepada format baru.");

        return self::SUCCESS;
    }
}