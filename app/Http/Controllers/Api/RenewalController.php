<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LsankLicense;
use App\Models\LsankRenewalApplication;
use Illuminate\Http\Request;

class RenewalController extends Controller
{
    /**
     * Number of days before expiry when a user
     * is allowed to begin a renewal.
     */
    private const RENEWAL_WINDOW_DAYS = 60;

    /**
     * Return licences belonging to the logged-in user
     * that are eligible for renewal.
     */
    public function eligible(Request $request)
    {
        $userId = (int) $request->user()->user_id;

        $today = now()->startOfDay();

        $renewalWindowEnd = $today
            ->copy()
            ->addDays(self::RENEWAL_WINDOW_DAYS);

        $query = LsankLicense::query()
            ->with([
                'application',
                'status',
            ])

            /*
             * Only licences connected to an application
             * belonging to the logged-in user.
             *
             * This automatically excludes orphan licences.
             */
            ->whereHas(
                'application',
                function ($applicationQuery) use ($userId) {
                    $applicationQuery->where(
                        'user_id',
                        $userId
                    );
                }
            )

            /*
             * A licence without an expiry date cannot
             * be renewed automatically.
             */
            ->whereNotNull('expiry_date')

            /*
             * Include:
             * - already expired licences; and
             * - licences expiring within 60 days.
             *
             * A lower date limit is intentionally not used
             * because expired licences may have old dates.
             */
            ->whereDate(
                'expiry_date',
                '<=',
                $renewalWindowEnd->toDateString()
            )

            /*
             * Exclude licences that already have an
             * unfinished renewal.
             */
            ->whereDoesntHave(
                'renewals',
                function ($renewalQuery) {
                    $renewalQuery->whereNotIn(
                        'renewal_status',
                        [
                            LsankRenewalApplication::STATUS_COMPLETED,
                            LsankRenewalApplication::STATUS_REJECTED,
                            LsankRenewalApplication::STATUS_CANCELLED,
                        ]
                    );
                }
            );

        /*
         * Optional search used by the Flutter search box.
         */
        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where(
                        'license_no',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'file_no',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'holder_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'activity_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'activity_location',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
         * Optional licence-type filter.
         *
         * Accepted examples:
         * water
         * effluent
         * Aktiviti Badan Perairan
         * Aktiviti Pelepasan Efluen
         */
        if ($request->filled('license_type')) {
            $licenseType = strtolower(
                trim(
                    (string) $request->input('license_type')
                )
            );

            if (in_array(
                $licenseType,
                ['water', 'perairan', 'badan perairan'],
                true
            )) {
                $query->where(
                    'license_type',
                    'Aktiviti Badan Perairan'
                );
            } elseif (in_array(
                $licenseType,
                ['effluent', 'efluen'],
                true
            )) {
                $query->where(
                    'license_type',
                    'Aktiviti Pelepasan Efluen'
                );
            } else {
                $query->where(
                    'license_type',
                    $request->input('license_type')
                );
            }
        }

        $licenses = $query
            ->orderBy('expiry_date')
            ->orderBy('license_id')
            ->get()

            /*
             * Run the final model-level check as a second
             * layer of protection.
             */
            ->filter(
                fn(LsankLicense $license) =>
                $license->canBeRenewedBy(
                    $userId,
                    self::RENEWAL_WINDOW_DAYS
                )
            )
            ->values();

        $formattedLicenses = $licenses
            ->map(
                fn(LsankLicense $license) =>
                $this->formatEligibleLicense($license)
            )
            ->values();

        $waterCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->application?->isWaterApplication()
                    ?? false
            )
            ->count();

        $effluentCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->application?->isEffluentApplication()
                    ?? false
            )
            ->count();

        $expiredCount = $licenses
            ->filter(
                fn(LsankLicense $license) =>
                $license->is_expired
            )
            ->count();

        return response()->json([
            'success' => true,

            'renewal_window_days' =>
            self::RENEWAL_WINDOW_DAYS,

            'summary' => [
                'total' => $licenses->count(),
                'water' => $waterCount,
                'effluent' => $effluentCount,
                'expired' => $expiredCount,
                'expiring' =>
                $licenses->count() - $expiredCount,
            ],

            'licenses' => $formattedLicenses,
        ]);
    }

    /**
     * Prepare one licence for the Flutter renewal list.
     */
    private function formatEligibleLicense(
        LsankLicense $license
    ): array {
        $application = $license->application;

        $isExpired = $license->is_expired;

        return [
            'license_id' =>
            (int) $license->license_id,

            'application_id' =>
            (int) $license->application_id,

            'file_no' =>
            (string) ($license->file_no ?? ''),

            'license_no' =>
            (string) ($license->license_no ?? ''),

            'holder_name' =>
            (string) ($license->holder_name ?? ''),

            'license_type' =>
            (string) ($license->license_type ?? ''),

            'application_type' =>
            (string) (
                $application?->application_type
                ?? (
                    $application?->isEffluentApplication()
                    ? 'effluent'
                    : 'water'
                )
            ),

            'applicant_type' =>
            (string) (
                $application?->applicant_type
                ?? 'Individu'
            ),

            'activity' =>
            (string) ($license->activity_name ?? ''),

            'location' =>
            (string) (
                $license->activity_location
                ?: $application?->district
                ?: '-'
            ),

            'start_date' =>
            $license->start_date?->format('d/m/Y'),

            'end_date' =>
            $license->expiry_date?->format('d/m/Y'),

            'status' =>
            $isExpired
                ? 'Tamat Tempoh'
                : 'Akan Tamat',

            'renewal_state' =>
            $isExpired
                ? 'expired'
                : 'expiring',

            'days_until_expiry' =>
            $license->days_until_expiry,

            'can_renew' => true,
        ];
    }
}
