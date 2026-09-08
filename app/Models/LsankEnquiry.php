<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankEnquiry extends Model
{
    protected $table = 'lsank_enquiries';

    protected $primaryKey = 'enquiry_id';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];
}
