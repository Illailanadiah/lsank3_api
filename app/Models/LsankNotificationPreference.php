<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNotificationPreference extends Model
{
    protected $table = 'lsank_notification_preferences';
    protected $primaryKey = 'preference_id';

    protected $fillable = [
        'user_id',
        'in_app_enabled',
        'push_enabled',
        'email_enabled',
        'whatsapp_enabled',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'in_app_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
    ];
}