<?php

namespace App\Services;

use App\Models\LsankApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationReferenceService
{
    /**
     * Assign 600-CC/X/YYYY once. Safe when multiple requests run together.
     */
    public function assign(LsankApplication $application): string
    {
        if ($this->isFinalReference($application->application_ref_no)) {
            return $application->application_ref_no;
        }

        return DB::transaction(function () use ($application): string {
            /** @var LsankApplication $locked */
            $locked = LsankApplication::query()
    ->with([
        'category.applicationType',
        'districtMaster',
    ])
    ->lockForUpdate()
    ->findOrFail($application->getKey());

            if ($this->isFinalReference($locked->application_ref_no)) {
                return $locked->application_ref_no;
            }

           if (!$locked->category || !$locked->districtMaster) {
    throw ValidationException::withMessages([
        'district_id' => 'Kategori aktiviti dan daerah mesti dipilih.',
    ]);
}

            if ((bool) $locked->is_one_off !== (bool) $locked->category->is_one_off) {
                throw ValidationException::withMessages([
                    'is_one_off' => 'Pilihan sekali beri tidak sepadan dengan kategori permohonan.',
                ]);
            }

            if ($locked->category->is_one_off && $locked->category->applicationType?->type_code !== 'WATER') {
                throw ValidationException::withMessages([
                    'is_one_off' => 'Aktiviti sekali beri hanya dibenarkan untuk Badan Perairan.',
                ]);
            }

            DB::table('lsank_file_number_sequences')->insertOrIgnore([
                'category_id' => $locked->category_id,
                'district_id' => $locked->district_id,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('lsank_file_number_sequences')
                ->where('category_id', $locked->category_id)
                ->where('district_id', $locked->district_id)
                ->lockForUpdate()
                ->first();

            $next = ((int) $sequence->last_number) + 1;

            DB::table('lsank_file_number_sequences')
                ->where('sequence_id', $sequence->sequence_id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

          $reference = sprintf(
    '%s/%d/%04d',
    $locked->category->reference_prefix,
    $locked->districtMaster->district_code,
    $next
);
            $locked->forceFill([
                'application_ref_no' => $reference,
                'file_running_number' => $next,
            ])->save();

            return $reference;
        }, 5);
    }

    private function isFinalReference(?string $reference): bool
    {
        return is_string($reference)
            && preg_match('/^600-\d{2}\/\d{1,2}\/\d+$/', $reference) === 1;
    }
}
