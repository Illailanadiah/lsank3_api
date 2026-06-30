<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicationStatus extends Model
{
    protected $table = 'lsank_application_statuses';

    protected $primaryKey = 'application_status_id';

    protected $fillable = [
        'status_name',
        'status_code',
        'description',
        'sort_order',
    ];
}