<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankPostcode extends Model
{
    protected $table = 'lsank_postcodes';

    protected $primaryKey = 'postcode_id';

    protected $fillable = [
        'district_id',
        'city_name',
        'postcode',
        'state_name',
        'status',
    ];

    public function district()
    {
        return $this->belongsTo(LsankDistrict::class, 'district_id', 'district_id');
    }
}