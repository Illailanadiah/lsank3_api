<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankWaterBodyApplication extends Model
{
    protected $table = 'lsank_water_body_applications';
    protected $primaryKey = 'water_body_id';

    protected $fillable = [
        'application_id',
        'activity_type_id',
        'activity_location',
        'longitude',
        'latitude',
        'operating_days',
        'operating_time',
        'motorized_fee',
        'non_motorized_fee',
        'activity_details',
    ];

    public function application()
    {
        return $this->belongsTo(LsankApplication::class, 'application_id', 'application_id');
    }
}