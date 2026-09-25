<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicant extends Model
{
    protected $table = 'lsank_applicants';

    protected $primaryKey = 'applicant_id';

    protected $fillable = [
        'user_id',
        'applicant_type',
        'applicant_name',
        'identity_no',
        'email',
        'phone',
        'phone_no',
        'address',
        'status',
    ];
    
    protected $casts = [
    'draft_data' => 'array',
    'submitted_data' => 'array',
    'review_data' => 'array',
    'recreation_details' => 'array',
    'officers' => 'array',
    'submitted_at' => 'datetime',
];

    public function user()
    {
        return $this->belongsTo(LsankUser::class, 'user_id', 'user_id');
    }

    public function company()
    {
        return $this->hasOne(LsankCompany::class, 'applicant_id', 'applicant_id');
    }

    public function applications()
    {
        return $this->hasMany(LsankApplication::class, 'applicant_id', 'applicant_id');
    }
}