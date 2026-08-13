<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsankNotice extends Model
{
    use HasFactory;

    protected $table = 'lsank_notices';

    protected $primaryKey = 'notice_id';

    protected $fillable = [
        'notice_no',
        'template_code',
        'notice_type',
        'category',
        'status',
        'okn_name',
        'identity_no',
        'registered_address',
        'phone',
        'offence_section',
        'activity_category',
        'offence_details',
        'offence_location',
        'inspection_date',
        'inspection_time',
        'coordinates',
        'compound_amount',
        'compound_status',
        'compound_due_date',
        'form_data',
        'created_by',
        'updated_by',
        'issued_at',
    ];

    protected $casts = [
        'form_data' => 'array',
        'inspection_date' => 'date',
        'compound_due_date' => 'date',
        'compound_amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];
}
