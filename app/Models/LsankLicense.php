<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LsankLicenseTerminationRequest;
use App\Models\LsankAmendmentApplication;

class LsankLicense extends Model
{
    use HasFactory;

    protected $table = 'lsank_licenses';

    protected $primaryKey = 'license_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

   protected $fillable = [
    'license_no',
    'file_no',
    'application_id',
    'holder_name',
    'license_type',
    'activity_name',
    'activity_location',
    'latitude',
    'longitude',
    'start_date',
    'expiry_date',
    'license_status_id',
    'qr_token',
    'qr_payload_hash',
    'qr_code_path',
    'license_pdf_path',
    'generated_at',
    'pdf_downloaded_at',
    'printed_at',
    'qr_downloaded_at',
];

   protected $casts = [
    'latitude' => 'decimal:8',
    'longitude' => 'decimal:8',
    'start_date' => 'date',
    'expiry_date' => 'date',
    'generated_at' => 'datetime',
    'pdf_downloaded_at' => 'datetime',
    'printed_at' => 'datetime',
    'qr_downloaded_at' => 'datetime',
];


    protected $with = [
        'terminationRequest',
    ];

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function status()
    {
        return $this->belongsTo(
            LsankLicenseStatus::class,
            'license_status_id',
            'license_status_id'
        );
    }
    /**
     * All renewal attempts made for this licence.
     */
    public function renewals()
    {
        return $this->hasMany(
            LsankRenewalApplication::class,
            'license_id',
            'license_id'
        );
    }

    /**
     * All license termination requests.
     */
    public function terminationRequests()
    {
        return $this->hasMany(
            LsankLicenseTerminationRequest::class,
            'license_id',
            'license_id'
        );
    }

    /**
     * Latest license termination request.
     */
    public function terminationRequest()
    {
        return $this->hasOne(
            LsankLicenseTerminationRequest::class,
            'license_id',
            'license_id'
        )->ofMany(
            'termination_request_id',
            'max'
        );
    }
    /**
     * Return the number of days before the licence expires.
     *
     * Positive: licence is still active.
     * Zero: licence expires today.
     * Negative: licence has expired.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) now()
            ->startOfDay()
            ->diffInDays(
                $this->expiry_date->copy()->startOfDay(),
                false
            );
    }

    /**
     * Check whether the licence will expire within
     * the permitted renewal period.
     */
    public function isWithinRenewalWindow(
        int $renewalWindowDays = 60
    ): bool {
        $daysUntilExpiry = $this->days_until_expiry;

        if ($daysUntilExpiry === null) {
            return false;
        }

        return $daysUntilExpiry >= 0
            && $daysUntilExpiry <= $renewalWindowDays;
    }

    /**
     * Check whether this licence already has an
     * unfinished renewal application.
     */
    public function hasOpenRenewal(): bool
    {
        return $this->renewals()
            ->whereNotIn('renewal_status', [
                LsankRenewalApplication::STATUS_COMPLETED,
                LsankRenewalApplication::STATUS_REJECTED,
                LsankRenewalApplication::STATUS_CANCELLED,
            ])
            ->exists();
    }

    /**
     * Check whether the licence belongs to a user.
     *
     * Ownership is obtained through:
     * licence -> original application -> user_id
     */
    public function belongsToUser(int $userId): bool
    {
        if ($this->relationLoaded('application')) {
            return (int) $this->application?->user_id === $userId;
        }

        return $this->application()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Final check before a user can start a renewal.
     */
    public function canBeRenewedBy(
        int $userId,
        int $renewalWindowDays = 60
    ): bool {
        /*
     * The original application must exist and
     * belong to the logged-in user.
     */
        if (!$this->belongsToUser($userId)) {
            return false;
        }

        /*
     * A licence without an expiry date cannot
     * be processed automatically.
     */
        if ($this->expiry_date === null) {
            return false;
        }

        /*
     * A licence is eligible when:
     * 1. It has already expired; or
     * 2. It will expire within the next 60 days.
     */
        $eligibleByDate = $this->is_expired
            || $this->isWithinRenewalWindow(
                $renewalWindowDays
            );

        if (!$eligibleByDate) {
            return false;
        }

        /*
     * Do not create a second renewal while another
     * renewal for this licence is still open.
     */
        if ($this->hasOpenRenewal()) {
            return false;
        }

        return true;
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return now()
            ->startOfDay()
            ->greaterThan(
                $this->expiry_date->copy()->startOfDay()
            );
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->is_expired) {
            return 'Tamat';
        }

        return $this->status?->status_name ?? 'Aktif';
    }

    public function getCanDownloadPdfAttribute(): bool
    {
        return $this->pdf_downloaded_at === null;
    }

    public function getCanPrintAttribute(): bool
    {
        return $this->printed_at === null;
    }

    public function getCanDownloadQrAttribute(): bool
    {
        return $this->qr_downloaded_at === null;
    }

    public function amendments()
    {
        return $this->hasMany(
            LsankAmendmentApplication::class,
            'license_id',
            'license_id'
        );
    }

    public function activeAmendment()
    {
        return $this->hasOne(
            LsankAmendmentApplication::class,
            'license_id',
            'license_id'
        )
            ->whereNotIn('status', [
                LsankAmendmentApplication::STATUS_COMPLETED,
                LsankAmendmentApplication::STATUS_REJECTED,
                LsankAmendmentApplication::STATUS_CANCELLED,
            ])
            ->latestOfMany('amendment_id');
    }

    public function hasOpenAmendment(): bool
    {
        return $this->amendments()
            ->whereNotIn('status', [
                LsankAmendmentApplication::STATUS_COMPLETED,
                LsankAmendmentApplication::STATUS_REJECTED,
                LsankAmendmentApplication::STATUS_CANCELLED,
            ])
            ->exists();
    }
}
