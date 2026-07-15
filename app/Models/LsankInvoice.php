<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankInvoice extends Model
{
    protected $table = 'lsank_invoices';
    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'invoice_no',
        'application_id',
        'license_id',
        'user_id',
        'invoice_date',
        'due_date',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function receipt()
    {
        return $this->hasOne(
            LsankReceipt::class,
            'invoice_id',
            'invoice_id'
        );
    }
}