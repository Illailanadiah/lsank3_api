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

    protected function generateReferenceNo(string $prefix, int $typeId): string
    {
        $year = now()->format('Y');

        $count = LsankApplication::whereYear('created_at', $year)
            ->where('application_type_id', $typeId)
            ->count() + 1;

        return $prefix . '-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    protected function formatApplicationListItem(LsankApplication $application, string $fallbackType): array
    {
        $detail = $application->waterBody ?: $application->effluent;

        return [
            'id' => $application->application_id,
            'application_id' => $application->application_id,
            'application_no' => $application->application_ref_no,
            'application_ref_no' => $application->application_ref_no,
            'type' => $application->type?->type_name ?? $fallbackType,
            'application_type' => $application->type?->type_name ?? $fallbackType,
            'applicant_name' => $application->applicant?->applicant_name ?? '-',
            'status' => $application->status?->status_name ?? 'Draf',
            'status_code' => $application->status?->status_code ?? 'draft',
            'submitted_date' => $application->submitted_at?->format('Y-m-d') ?? '-',
            'submitted_at' => $application->submitted_at?->toDateTimeString(),
            'activity_location' => $detail?->activity_location ?? '-',
            'created_at' => $application->created_at?->toDateTimeString(),
        ];
    }

    protected function formatApplicationDetail(LsankApplication $application, string $fallbackType): array
    {
        return [
            ...$this->formatApplicationListItem($application, $fallbackType),
            'user' => $application->user,
            'applicant' => $application->applicant,
            'company' => $application->applicant?->company,
            'water_body' => $application->waterBody,
            'effluent' => $application->effluent,
            'documents' => $application->documents,
            'reviews' => $application->reviews,
            'remarks' => $application->remarks,
        ];
    }
}
