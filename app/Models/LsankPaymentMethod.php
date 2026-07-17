<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankPaymentMethod extends Model
{
    protected $table = 'lsank_payment_methods';

    protected $primaryKey = 'payment_method_id';

    protected $fillable = [
        'method_name',
        'method_code',
        'status',
    ];

    public function payments()
    {
        return $this->hasMany(
            LsankPayment::class,
            'payment_method_id',
            'payment_method_id'
        );
    }
}