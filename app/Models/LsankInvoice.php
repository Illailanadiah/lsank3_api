<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'payment_type',
        'total_amount',
        'status',

        'security_refund_status',
        'security_refund_requested_at',
        'security_refunded_at',
        'security_refunded_by',
        'security_refund_note',

        'security_refund_voucher_no',
        'security_refund_voucher_date',
        'security_refund_amount',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',

        'security_refund_requested_at' => 'datetime',
        'security_refunded_at' => 'datetime',

        'security_refund_voucher_date' => 'date',
        'security_refund_amount' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function payment(): HasOne
    {
        return $this->hasOne(
            LsankPayment::class,
            'invoice_id',
            'invoice_id'
        );
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(
            LsankReceipt::class,
            'invoice_id',
            'invoice_id'
        );
    }
}
