<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsankLicenseTerminationRequest extends Model
{
    use HasFactory;

    protected $table =
    'lsank_license_termination_requests';

    protected $primaryKey =
    'termination_request_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'license_id',
        'application_id',
        'application_type',
        'reason',
        'termination_status',
        'requested_by_user_id',
        'requested_at',
        'decided_by_user_id',
        'director_remark',
        'decided_at',
        'security_refund_status',
        'security_refund_amount',
        'security_refund_reference',
        'security_refund_note',
        'security_refunded_at',
        'security_refunded_by_user_id',
    ];

    protected $casts = [
        'termination_request_id' => 'integer',
        'license_id' => 'integer',
        'application_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'decided_by_user_id' => 'integer',

        'security_refund_amount' => 'decimal:2',
        'security_refunded_by_user_id' => 'integer',

        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'security_refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function license()
    {
        return $this->belongsTo(
            LsankLicense::class,
            'license_id',
            'license_id'
        );
    }

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }
}
