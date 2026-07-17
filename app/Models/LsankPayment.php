<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\LsankInvoice;
use App\Models\LsankPaymentMethod;
use App\Models\LsankReceipt;

class LsankPayment extends Model
{
    protected $table = 'lsank_payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'amount',
        'payment_status',
        'payment_date',
        'transaction_ref_no',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            LsankInvoice::class,
            'invoice_id',
            'invoice_id'
        );
    }

    public function paymentMethod()
    {
        return $this->belongsTo(
            LsankPaymentMethod::class,
            'payment_method_id',
            'payment_method_id'
        );
    }

    public function receipt()
    {
        return $this->hasOne(
            LsankReceipt::class,
            'payment_id',
            'payment_id'
        );
    }
}