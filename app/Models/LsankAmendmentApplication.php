<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankAmendmentApplication extends Model
{
    protected $table = 'lsank_amendment_applications';

    protected $primaryKey = 'amendment_id';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING_FEE_PENDING =
    'processing_fee_pending';

    public const STATUS_UNDER_REVIEW =
    'under_review';

    public const STATUS_APPROVED_PENDING_PAYMENT =
    'approved_pending_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_FORM_A = 'form_a';

    public const TYPE_MIXED = 'mixed';

    protected $fillable = [
        'application_id',
        'license_id',
        'amendment_type',
        'old_information',
        'new_information',
        'reason',
        'status',
    ];

    protected $casts = [
        'amendment_id' => 'integer',
        'application_id' => 'integer',
        'license_id' => 'integer',

        /*
         * Column database ialah longtext tetapi kandungannya
         * disimpan sebagai JSON.
         */
        'old_information' => 'array',
        'new_information' => 'array',
    ];

    /**
     * Permohonan pindaan baharu yang boleh diedit.
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
     * Lesen asal yang hendak dipinda.
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
     * Permohonan asal yang menghasilkan lesen.
     */
    public function sourceApplication()
    {
        return $this->license?->application;
    }

    public function isClosed(): bool
    {
        return in_array(
            strtolower((string) $this->status),
            [
                self::STATUS_COMPLETED,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            true
        );
    }

    public function isFormAEnabled(): bool
    {
        $newInformation = $this->new_information ?? [];

        return (bool) (
            $newInformation['meta']['form_a_edit_enabled']
            ?? false
        );
    }
}
