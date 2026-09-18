<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicantType extends Model
{
    protected $table = 'lsank_applicant_types';
    protected $primaryKey = 'applicant_type_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['applicant_type_id', 'type_code', 'type_name', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
