<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankCompanyOfficer extends Model
{
    protected $table = 'lsank_company_officers';
    protected $primaryKey = 'company_officer_id';

    protected $fillable = [
        'company_id',
        'officer_name',
        'officer_phone',
        'officer_position',
    ];

    public function company()
    {
        return $this->belongsTo(
            LsankCompany::class,
            'company_id',
            'company_id'
        );
    }
}