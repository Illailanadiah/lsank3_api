<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsankReport extends Model
{
    use HasFactory;

    protected $table = 'lsank_reports';

    protected $primaryKey = 'report_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'application_id',
        'report_name',
        'report_type',
        'activity_name',
        'report_status',
        'description',
        'report_data',
        'security_amount',
        'government_project',
        'project_invoice_mode',
        'invoice_generate',
        'invoice_fee_caj',
        'invoice_fee_lesen',
        'invoice_fee_sekuriti',
        'invoice_exempt',
        'license_start_date',
        'license_end_date',
        'submitted_at',
        'created_by',
    ];

    protected $casts = [
        'report_data' => 'array',
        'security_amount' => 'decimal:2',
        'invoice_generate' => 'boolean',
        'invoice_fee_caj' => 'decimal:2',
        'invoice_fee_lesen' => 'decimal:2',
        'invoice_fee_sekuriti' => 'decimal:2',
        'invoice_exempt' => 'boolean',
        'license_start_date' => 'date',
        'license_end_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            LsankUser::class,
            'created_by',
            'user_id'
        );
    }
}

