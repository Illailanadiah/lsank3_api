<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'license_id' => 'integer',
        'application_id' => 'integer',
        'license_status_id' => 'integer',

        'start_date' => 'date',
        'expiry_date' => 'date',

        'generated_at' => 'datetime',
        'pdf_downloaded_at' => 'datetime',
        'printed_at' => 'datetime',
        'qr_downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
}