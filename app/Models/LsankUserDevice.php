<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankUserDevice extends Model
{
    protected $table = 'lsank_user_devices';
    protected $primaryKey = 'device_id';

    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'device_name',
        'app_version',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];
}