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
        'draft_data',
    ];

    protected $casts = [
        'draft_data' => 'array',
        'longitude' => 'decimal:7',
        'latitude' => 'decimal:7',
        'motorized_fee' => 'decimal:2',
        'non_motorized_fee' => 'decimal:2',
    ];

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function activityType()
    {
        return $this->belongsTo(
            LsankActivityType::class,
            'activity_type_id',
            'activity_type_id'
        );
    }

    public function vesselDetails()
{
    return $this->hasMany(
        LsankWaterBodyVesselDetail::class,
        'water_body_id',
        'water_body_id'
    );
}
}