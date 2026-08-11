<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankRenewalApplication extends Model
{
    protected $table = 'lsank_renewal_applications';

    protected $primaryKey = 'renewal_id';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'application_id',
        'license_id',
        'old_expiry_date',
        'new_expiry_date',
        'renewal_status',
    ];

    protected $casts = [
        'renewal_id' => 'integer',
        'application_id' => 'integer',
        'license_id' => 'integer',
        'old_expiry_date' => 'date',
        'new_expiry_date' => 'date',
    ];

    /**
     * New renewal application created from the existing licence.
     */
    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    /**
     * Original licence being renewed.
     */
    public function license()
    {
        return $this->belongsTo(
            LsankLicense::class,
            'license_id',
            'license_id'
        );
    }

    /**
     * Check whether this renewal process has ended.
     */
    public function isClosed(): bool
    {
        return in_array(
            strtolower((string) $this->renewal_status),
            [
                self::STATUS_COMPLETED,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            true
        );
    }
}
