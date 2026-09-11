<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankActivityType extends Model
{
    protected $table = 'lsank_activity_types';

    protected $primaryKey = 'activity_type_id';

    protected $fillable = [
        'activity_name',
        'activity_code',
        'description',
        'status',
    ];

    public function waterBodyApplications()
    {
        return $this->hasMany(
            LsankWaterBodyApplication::class,
            'activity_type_id',
            'activity_type_id'
        );
    }
}