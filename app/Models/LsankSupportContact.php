<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankSupportContact extends Model
{
    protected $table = 'lsank_support_contacts';

    protected $primaryKey = 'contact_id';

    protected $fillable = [
        'organization_name',
        'address',
        'phone',
        'email',
        'website',
        'operating_hours',
        'map_url',
    ];
}
