<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class LsankApplication extends Model
{
    protected $table = 'lsank_applications';

    protected $primaryKey = 'application_id';

    public const STATUS_DRAF = 'draf';
    public const STATUS_FI_PEMPROSESAN = 'fi_pemprosesan';
    public const STATUS_DALAM_PROSES = 'dalam_proses';
    public const STATUS_LULUS = 'lulus';
    public const STATUS_GAGAL = 'gagal';

    public const PAYMENT_BELUM_BAYAR = 'belum_bayar';
    public const PAYMENT_MENUNGGU_BAYARAN = 'menunggu_bayaran';
    public const PAYMENT_SUDAH_BAYAR = 'sudah_bayar';

    protected $fillable = [
        'application_ref_no',
        'user_id',

        'applicant_name',
        'business_name',
        'phone',
        'email',

        'license_type',
        'activity_type',
        'application_type',
        'payment_status',
        'application_status',
        'current_step',

        'activity_name',
        'district',
        'activity_location',
        'longitude',
        'latitude',
        'activity_details',

        'submitted_at',
        'remarks',

        'draft_data',
        'review_data',
        'submitted_data',

        // Legacy fields. Keep until older controllers/screens are migrated.
        'applicant_id',
        'application_type_id',
        'application_status_id',
        'application_category',

        'applicant_type',
        'identity_no',
        'phone_no',
        'address',

        'company_name',
        'registration_no',
        'business_address',
        'business_phone',
        'business_email',

        'responsible_officer_name',
        'responsible_officer_phone',
        'responsible_officer_position',
        'officers',

        'activity_type_id',
        'operating_days',
        'operating_time',
        'recreation_details',
        'construction_shape',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',

        'draft_data' => 'array',
        'review_data' => 'array',
        'submitted_data' => 'array',

        'officers' => 'array',
        'recreation_details' => 'array',

        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(
            LsankUser::class,
            'user_id',
            'user_id'
        );
    }

    public function applicant()
    {
        return $this->belongsTo(
            LsankApplicant::class,
            'applicant_id',
            'applicant_id'
        );
    }

    public function type()
    {
        return $this->belongsTo(
            LsankApplicationType::class,
            'application_type_id',
            'application_type_id'
        );
    }

    /**
     * Alias used by LicenseController and other newer code.
     */
    public function applicationType()
    {
        return $this->type();
    }

    public function status()
    {
        return $this->belongsTo(
            LsankApplicationStatus::class,
            'application_status_id',
            'application_status_id'
        );
    }

    public function waterBody()
    {
        return $this->hasOne(
            LsankWaterBodyApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function effluent()
    {
        return $this->hasOne(
            LsankEffluentApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function documents()
    {
        return $this->hasMany(
            LsankApplicationDocument::class,
            'application_id',
            'application_id'
        );
    }

    public function reviews()
    {
        return $this->hasMany(
            LsankApplicationReview::class,
            'application_id',
            'application_id'
        );
    }

    public function license()
    {
        return $this->hasOne(
            LsankLicense::class,
            'application_id',
            'application_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->application_status) {
            self::STATUS_DRAF => 'Draf',
            self::STATUS_FI_PEMPROSESAN => 'Fi Pemprosesan',
            self::STATUS_DALAM_PROSES => 'Dalam Proses',
            self::STATUS_LULUS => 'Lulus',
            self::STATUS_GAGAL => 'Gagal',
            default => 'Draf',
        };
    }

    public function getDisplayPaymentStatusAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_BELUM_BAYAR => 'Belum Bayar',
            self::PAYMENT_MENUNGGU_BAYARAN => 'Menunggu Bayaran',
            self::PAYMENT_SUDAH_BAYAR => 'Sudah Bayar',
            default => 'Belum Bayar',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isWaterApplication(): bool
    {
        return $this->application_type === 'water'
            || $this->license_type === 'Aktiviti Badan Perairan';
    }

    public function isEffluentApplication(): bool
    {
        return $this->application_type === 'effluent'
            || $this->license_type === 'Aktiviti Pelepasan Efluen';
    }

    public function isDraft(): bool
    {
        return $this->application_status === self::STATUS_DRAF;
    }

    public function isProcessingFeeStage(): bool
    {
        return $this->application_status === self::STATUS_FI_PEMPROSESAN;
    }

    public function isInProcess(): bool
    {
        return $this->application_status === self::STATUS_DALAM_PROSES;
    }

    public function isApproved(): bool
    {
        return $this->application_status === self::STATUS_LULUS;
    }

    public function isRejected(): bool
    {
        return $this->application_status === self::STATUS_GAGAL;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_SUDAH_BAYAR;
    }

    public function workflowStage(): string
    {
        $reviewData = is_array($this->review_data)
            ? $this->review_data
            : [];

        return strtolower(
            trim((string) (
                $reviewData['workflow_stage']
                ?? data_get($reviewData, 'meta.workflow_stage')
                ?? ''
            ))
        );
    }

    public function isDirectorApproved(): bool
    {
        return $this->isApproved()
            && $this->workflowStage() === 'director_approved';
    }
}