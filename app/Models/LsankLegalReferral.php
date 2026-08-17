<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankLegalReferral extends Model
{
    protected $table = 'lsank_legal_referrals';

    protected $primaryKey = 'legal_referral_id';

    protected $fillable = [
        'notice_id',
        'user_id',
        'license_id',
        'application_id',
        'notice_no',
        'notice_type',
        'category',
        'case_track',
        'referral_status',
        'notice_date',
        'due_at',
        'triggered_at',
        'civil_case_id',
        'criminal_case_id',
        'metadata',
    ];

    protected $casts = [
        'notice_date' => 'datetime',
        'due_at' => 'datetime',
        'triggered_at' => 'datetime',
        'metadata' => 'array',
    ];
}
