<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankCompany extends Model
{
    protected $table = 'lsank_companies';

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'applicant_id',
        'company_name',
        'registration_no',
        'business_address',
        'business_phone',
        'business_email',
        'responsible_officer_name',
        'responsible_officer_phone',
    ];

    public function applicant()
    {
        return $this->belongsTo(LsankApplicant::class, 'applicant_id', 'applicant_id');
    }
}