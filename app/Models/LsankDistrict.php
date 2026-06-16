<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankDistrict extends Model
{
    protected $table = 'lsank_districts';

    protected $primaryKey = 'district_id';

    protected $fillable = [
        'district_name',
        'state_name',
        'postcode_prefix',
        'status',
    ];

    public function postcodes()
    {
        return $this->hasMany(LsankPostcode::class, 'district_id', 'district_id');
    }
}