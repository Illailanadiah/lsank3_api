<?php

namespace App\Console\Commands;

use App\Models\LsankApplication;
use App\Models\LsankInvoice;
use Illuminate\Console\Command;

class SyncOldWaterInvoices extends Command
{
    protected $signature = 'lsank:sync-old-water-invoices';

    protected $description = 'Sync invoice lama permohonan badan perairan ke lsank_invoices';

    public function handle(): int
    {
        $applications = LsankApplication::query()
            ->where(function ($query) {
                $query->where('application_type', 'water')
                    ->orWhere('license_type', 'Aktiviti Badan Perairan');
            })
            ->whereNotIn('application_status', [
                LsankApplication::STATUS_DRAF,
            ])
            ->get();

        $synced = 0;

        foreach ($applications as $application) {
            $year = optional($application->created_at)->format('Y') ?? now()->format('Y');
            $runningNo = str_pad($application->application_id, 4, '0', STR_PAD_LEFT);

            $invoiceNo = 'INVOIS-' . $year . '-' . $runningNo . '-01';

            $draftData = is_array($application->draft_data)
                ? $application->draft_data
                : [];

            $selectedActivities = $draftData['selected_activities'] ?? [];

            if (!is_array($selectedActivities) || empty($selectedActivities)) {
                $rawActivities = $application->activity_details
                    ?? $application->activity_name
                    ?? $application->activity_type
                    ?? 'Aktiviti Rekreasi Sukan Air';

                $selectedActivities = collect(explode(',', $rawActivities))
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();
            }

            $activityCount = collect($selectedActivities)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->count();

            if ($activityCount < 1) {
                $activityCount = 1;
            }

            $processingFee = 150 * $activityCount;

            $isPaid = in_array($application->application_status, [
                LsankApplication::STATUS_DALAM_PROSES,
                LsankApplication::STATUS_LULUS,
                LsankApplication::STATUS_GAGAL,
            ], true) || $application->payment_status === LsankApplication::PAYMENT_SUDAH_BAYAR;

            LsankInvoice::updateOrCreate(
                [
                    'invoice_no' => $invoiceNo,
                ],
                [
                    'application_id' => $application->application_id,
                    'user_id' => $application->user_id,
                    'invoice_date' => optional($application->created_at)->toDateString() ?? now()->toDateString(),
                    'due_date' => optional($application->created_at)?->copy()->addDays(14)->toDateString()
                        ?? now()->addDays(14)->toDateString(),
                    'total_amount' => $processingFee,
                    'status' => $isPaid ? 'paid' : 'unpaid',
                ]
            );

            $synced++;
        }

        $this->info("Selesai sync {$synced} invoice lama ke lsank_invoices.");

        return self::SUCCESS;
    }
}