<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankWaterBodyVesselDetail extends Model
{
    protected $table = 'lsank_water_body_vessel_details';

    protected $primaryKey = 'vessel_detail_id';

    protected $fillable = [
        'water_body_id',
        'application_id',
        'vessel_type',
        'vessel_name',
        'registration_no',
        'passenger_capacity',
    ];

    protected $casts = [
        'water_body_id' => 'integer',
        'application_id' => 'integer',
        'passenger_capacity' => 'integer',
    ];

    public function waterBody()
    {
        return $this->belongsTo(
            LsankWaterBodyApplication::class,
            'water_body_id',
            'water_body_id'
        );
    }

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }
}