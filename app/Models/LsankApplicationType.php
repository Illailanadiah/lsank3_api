<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicationType extends Model

{

    protected $table = 'lsank_application_types';

    protected $primaryKey = 'application_type_id';

    protected $fillable = [

        'type_name',

        'type_code',

        'description',

        'status',

    ];

}