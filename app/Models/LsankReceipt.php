<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankReceipt extends Model
{
    protected $table = 'lsank_receipts';

    protected $primaryKey = 'receipt_id';

    protected $fillable = [
        'receipt_no',
        'invoice_id',
        'payment_id',
        'receipt_date',
        'amount',
        'receipt_pdf_path',
        'status',
    ];

    protected $casts = [
        'receipt_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            LsankInvoice::class,
            'invoice_id',
            'invoice_id'
        );
    }

    public function payment()
    {
        return $this->belongsTo(
            LsankPayment::class,
            'payment_id',
            'payment_id'
        );
    }
}