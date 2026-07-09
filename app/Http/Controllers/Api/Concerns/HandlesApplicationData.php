<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\LsankApplication;
use App\Models\LsankApplicationStatus;
use App\Models\LsankApplicationType;
use Illuminate\Support\Facades\Schema;

trait HandlesApplicationData
{
    protected function applicationTypeId(string $code, string $name): int
    {
        $type = LsankApplicationType::firstOrCreate(
            ['type_code' => $code],
            [
                'type_name' => $name,
                'description' => $name,
                'status' => 'active',
            ]
        );

        return (int) $type->application_type_id;
    }

    protected function applicationStatusId(string $code, string $name, int $sortOrder = 0): int
    {
        $status = LsankApplicationStatus::firstOrCreate(
            ['status_code' => $code],
            [
                'status_name' => $name,
                'description' => $name,
                'sort_order' => $sortOrder,
            ]
        );

        return (int) $status->application_status_id;
    }

    protected function applicantPhoneColumn(): string
    {
        return Schema::hasColumn('lsank_applicants', 'phone_no')
            ? 'phone_no'
            : 'phone';
    }

    protected function normalizeApplicantType(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if (str_contains($value, 'syarikat') || str_contains($value, 'company')) {
            return 'company';
        }

        if (str_contains($value, 'agensi') || str_contains($value, 'agency')) {
            return 'agency';
        }

        return 'individual';
    }

    protected function districtCode(?string $district): string
    {
        $district = strtolower(trim((string) $district));
        $district = str_replace('daerah ', '', $district);

        return match ($district) {
            'kota setar' => '1',
            'kuala muda' => '2',
            'kulim' => '3',
            'kubang pasu' => '4',
            'baling' => '5',
            'sik' => '6',
            'padang terap' => '7',
            'langkawi' => '8',
            'yan' => '9',
            'bandar baharu' => '10',
            'pendang' => '11',
            'pokok sena' => '12',
            default => '0',
        };
    }

    protected function waterSectionCode(?string $activityName): string
    {
        $activityName = strtolower(trim((string) $activityName));

        if (
            str_contains($activityName, 'rekreasi sukan air') ||
            str_contains($activityName, '600-15')
        ) {
            return '600-15';
        }

        if (
            str_contains($activityName, 'vesel rekreasi') ||
            str_contains($activityName, '600-16')
        ) {
            return '600-16';
        }

        if (
            str_contains($activityName, 'sangkar') ||
            str_contains($activityName, '600-18')
        ) {
            return '600-18';
        }

        if (
            str_contains($activityName, 'binaan') ||
            str_contains($activityName, '600-19')
        ) {
            return '600-19';
        }

        return '600-15';
    }

    protected function generateApplicationFileNo(
        string $sectionCode,
        string $districtCode
    ): string {
        $latestRefNo = LsankApplication::where(
                'application_ref_no',
                'like',
                $sectionCode . '/' . $districtCode . '/%'
            )
            ->orderByDesc('application_id')
            ->value('application_ref_no');

        $nextNumber = 1;

        if ($latestRefNo) {
            $parts = explode('/', $latestRefNo);
            $lastNumber = (int) end($parts);

            if ($lastNumber > 0) {
                $nextNumber = $lastNumber + 1;
            }
        }

        $fileNumber = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return $sectionCode . '/' . $districtCode . '/' . $fileNumber;
    }

    protected function formatApplicationListItem(
        LsankApplication $application,
        string $fallbackType
    ): array {
        $waterBody = $application->waterBody;
        $effluent = $application->effluent;

        $activityName = $effluent?->serviceType?->service_name
            ?? $waterBody?->activity_details
            ?? '-';

        $activityLocation = $effluent?->activity_location
            ?? $waterBody?->activity_location
            ?? '-';

        return [
            'id' => $application->application_id,
            'application_id' => $application->application_id,
            'application_no' => $application->application_ref_no,
            'application_ref_no' => $application->application_ref_no,

            'type' => $application->type?->type_name ?? $fallbackType,
            'application_type' => $application->type?->type_name ?? $fallbackType,

            'activity_name' => $activityName,
            'service_name' => $effluent?->serviceType?->service_name,
            'service_type_id' => $effluent?->service_type_id,
            'service_code' => $effluent?->serviceType?->service_code,

            'applicant_name' => $application->applicant?->applicant_name ?? '-',
            'status' => $application->status?->status_name ?? 'Draf',
            'status_code' => $application->status?->status_code ?? 'draft',
            'submitted_date' => $application->submitted_at?->format('Y-m-d') ?? '-',
            'submitted_at' => $application->submitted_at?->toDateTimeString(),
            'activity_location' => $activityLocation,
            'created_at' => $application->created_at?->toDateTimeString(),
        ];
    }

    protected function formatApplicationDetail(
        LsankApplication $application,
        string $fallbackType
    ): array {
        $effluent = $application->effluent;

        return [
            ...$this->formatApplicationListItem($application, $fallbackType),
            'current_step' => $application->current_step,
            'draft_data' => $application->draft_data ?? [],
            'user' => $application->user,
            'applicant' => $application->applicant,
            'company' => $application->applicant?->company,
            'water_body' => $application->waterBody,
            'effluent' => $effluent ? [
                'effluent_id' => $effluent->effluent_id,
                'application_id' => $effluent->application_id,
                'service_type_id' => $effluent->service_type_id,
                'service_name' => $effluent->serviceType?->service_name,
                'activity_name' => $effluent->serviceType?->service_name,
                'service_code' => $effluent->serviceType?->service_code,
                'activity_location' => $effluent->activity_location,
                'longitude' => $effluent->longitude,
                'latitude' => $effluent->latitude,
                'composition' => $effluent->composition,
                'frequency' => $effluent->frequency,
                'flow_rate' => $effluent->flow_rate,
                'sampling_method' => $effluent->sampling_method,
                'contingency_plan' => $effluent->contingency_plan,
                'disposal_method' => $effluent->disposal_method,
                'created_at' => $effluent->created_at,
                'updated_at' => $effluent->updated_at,
            ] : null,
            'documents' => $application->documents,
            'reviews' => $application->reviews,
            'remarks' => $application->remarks,
        ];
    }
}