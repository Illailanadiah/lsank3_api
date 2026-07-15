<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankLicense extends Model
{
    protected $table = 'lsank_licenses';

    protected $primaryKey = 'license_id';

    public $incrementing = true;

    protected $keyType = 'int';

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
        'start_date' => 'date',
        'expiry_date' => 'date',
        'generated_at' => 'datetime',
        'pdf_downloaded_at' => 'datetime',
        'printed_at' => 'datetime',
        'qr_downloaded_at' => 'datetime',
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
}