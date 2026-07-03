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
        'applicant_id',
        'application_type_id',
        'application_status_id',
        'application_category',
        'submitted_at',
        'remarks',

        'applicant_name',
        'business_name',
        'phone',
        'email',
        'license_type',
        'activity_type',
        'application_type',
        'payment_status',
        'application_status',

        // draft/payment flow
        'current_step',
        'draft_data',

        // common applicant data
        'applicant_type',
        'identity_no',
        'phone_no',
        'address',

        // business data
        'company_name',
        'registration_no',
        'business_address',
        'business_phone',
        'business_email',

        // officer data
        'responsible_officer_name',
        'responsible_officer_phone',
        'responsible_officer_position',
        'officers',

        // activity/location data
        'activity_type_id',
        'activity_name',
        'district',
        'activity_location',
        'longitude',
        'latitude',
        'operating_days',
        'operating_time',
        'activity_details',
        'recreation_details',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'draft_data' => 'array',
        'officers' => 'array',
        'recreation_details' => 'array',
        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(LsankUser::class, 'user_id', 'user_id');
    }

    public function applicant()
    {
        return $this->belongsTo(LsankApplicant::class, 'applicant_id', 'applicant_id');
    }

    public function type()
    {
        return $this->belongsTo(LsankApplicationType::class, 'application_type_id', 'application_type_id');
    }

    public function status()
    {
        return $this->belongsTo(LsankApplicationStatus::class, 'application_status_id', 'application_status_id');
    }

    public function waterBody()
    {
        return $this->hasOne(LsankWaterBodyApplication::class, 'application_id', 'application_id');
    }

    public function effluent()
    {
        return $this->hasOne(LsankEffluentApplication::class, 'application_id', 'application_id');
    }

    public function documents()
    {
        return $this->hasMany(LsankApplicationDocument::class, 'application_id', 'application_id');
    }

    public function reviews()
    {
        return $this->hasMany(LsankApplicationReview::class, 'application_id', 'application_id');
    }

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
}