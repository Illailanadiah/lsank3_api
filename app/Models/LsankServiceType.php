<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankServiceType extends Model
{
    protected $table = 'lsank_service_types';

    protected $primaryKey = 'service_type_id';

    protected $fillable = [
        'service_name',
        'service_code',
        'description',
        'status',
    ];

    public function effluentApplications()
    {
        return $this->hasMany(
            LsankEffluentApplication::class,
            'service_type_id',
            'service_type_id'
        );
    }
}