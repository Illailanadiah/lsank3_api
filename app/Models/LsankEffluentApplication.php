<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankEffluentApplication extends Model
{
    protected $table = 'lsank_effluent_applications';
    protected $primaryKey = 'effluent_id';

    protected $fillable = [
        'application_id',
        'service_type_id',
        'activity_location',
        'longitude',
        'latitude',
        'composition',
        'frequency',
        'flow_rate',
        'sampling_method',
        'contingency_plan',
        'disposal_method',
    ];

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

   public function serviceType()
{
    return $this->belongsTo(
        \App\Models\LsankServiceType::class,
        'service_type_id',
        'service_type_id'
    );
}

}