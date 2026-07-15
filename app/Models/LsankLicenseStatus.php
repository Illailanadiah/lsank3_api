<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankLicenseStatus extends Model
{
    protected $table = 'lsank_license_statuses';

    protected $primaryKey = 'license_status_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'status_name',
        'status_code',
        'description',
    ];

    protected $casts = [
        'license_status_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function licenses()
    {
        return $this->hasMany(
            LsankLicense::class,
            'license_status_id',
            'license_status_id'
        );
    }
}